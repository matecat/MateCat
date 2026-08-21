<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/06/25
 * Time: 11:21
 *
 */

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Controller\Views\TemplateDecorator\Arguments\CatDecoratorArguments;
use Exception;
use Matecat\Locales\Languages;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\Hook\Event\Filter\AppendInitialTemplateVarsEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\JobStruct;
use Model\Jobs\LexiQaAndTagProjectionLanguages;
use Model\Jobs\MetadataDao;
use Model\LQA\CategoryDao;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Users\UserDao;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ReflectionException;
use RuntimeException;
use stdClass;
use Utils\Constants\SourcePages;
use Utils\Constants\Teams;
use Utils\Constants\TranslationStatus;
use Utils\Engines\Intento;
use Utils\Registry\AppConfig;
use Utils\Templating\PHPTalBoolean;
use Utils\Templating\PHPTalMap;
use Utils\Templating\PHPTalString;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;

class CattoolController extends BaseKleinViewController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new ViewLoginRedirectValidator($this));
    }

    /**
     * @return array{jid: string, password: string}
     *
     * @throws \TypeError
     */
    protected function validateTheRequest(): array
    {
        $filterArgs = [
            'jid' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ],
        ];

        $result = filter_var_array($this->request->paramsNamed()->all(), $filterArgs);

        return [
            'jid' => (string)($result['jid'] ?? ''),
            'password' => (string)($result['password'] ?? ''),
        ];
    }

    /**
     * Finds the current chunk from the job id and the presented password, and with it the phase the
     * request belongs to.
     *
     * The phase comes from the credential, never from the path. The same job id is reachable with the
     * job password or with the review password of any phase, and only the row the password matches
     * says which one this is: deciding it from the URL instead let a translator ask a /translate/…
     * link for the second reviewer's password. This mirrors {@see ChunkPasswordValidator}, which the
     * APIs the page then calls already resolve through.
     *
     * @throws Exception
     * @throws NotFoundException
     */
    private function findJobByIdAndPassword(int $job_id, string $password): stdClass
    {
        $chunk = (new JobDao($this->getDatabase()))->getByIdAndPassword($job_id, $password);

        if ($chunk !== null) {
            return (object)['chunk' => $chunk, 'chunkReviewStruct' => null];
        }

        $chunkReviewStruct = (new ChunkReviewDao($this->getDatabase()))->findByReviewPasswordAndJobId($password, $job_id);

        if ($chunkReviewStruct === null) {
            throw new NotFoundException('Review record was not found');
        }

        return (object)[
            'chunk' => $chunkReviewStruct->getChunk(new JobDao($this->getDatabase())),
            'chunkReviewStruct' => $chunkReviewStruct,
        ];
    }

    /**
     * @throws Exception
     * @throws \TypeError
     */
    public function renderView(): void
    {
        $chunkAndPasswords = new stdClass();
        $request = $this->validateTheRequest();
        $revisionNumber = null;

        try {
            $chunkAndPasswords = $this->findJobByIdAndPassword((int)$request['jid'], $request['password']);
            $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($chunkAndPasswords->chunkReviewStruct ? $chunkAndPasswords->chunkReviewStruct->source_page : null);
        } catch (NotFoundException) {
            $this->notFound();
        }

        /** @var JobStruct $chunkStruct */
        $chunkStruct = $chunkAndPasswords->chunk;

        /** @var ?ChunkReviewStruct $chunkReviewStruct */
        $chunkReviewStruct = $chunkAndPasswords->chunkReviewStruct;

        $isRevision = $chunkReviewStruct !== null;
        $sourcePage = $chunkReviewStruct->source_page ?? SourcePages::SOURCE_PAGE_TRANSLATE;

        $chunkId = $chunkStruct->id ?? throw new RuntimeException('Chunk id is null after successful load');
        $chunkPassword = $chunkStruct->password ?? throw new RuntimeException('Chunk password is null after successful load');
        $projectId = $chunkStruct->getProject(new ProjectDao($this->getDatabase()))->id ?? throw new RuntimeException('Project id is null');

        $jobOwnership = $this->findOwnerEmailAndTeam($chunkStruct->getProject(new ProjectDao($this->getDatabase())));

        if ($chunkStruct->isCanceled()) {
            $this->cancelled($jobOwnership);
        }

        if ($chunkStruct->isArchived()) {
            $this->archived(
                $chunkId,
                $isRevision ? ($chunkReviewStruct->review_password ?? $chunkPassword) : $chunkPassword,
                $jobOwnership
            );
        }

        if ($chunkStruct->isDeleted()) {
            $this->notFound();
        }

        $project = $chunkStruct->getProject(new ProjectDao($this->getDatabase()));
        $model = $project->id_qa_model !== null ? (new ModelDao($this->getDatabase()))->findById($project->id_qa_model) : null;
        $jobsMetadataDao = new MetadataDao($this->getDatabase());
        $public_tm_penalty = $jobsMetadataDao->get($chunkId, $chunkPassword, JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value);

        $this->setView("index.html", [
            'active_engine' => new PHPTalMap($this->getActiveEngine($chunkStruct->id_mt_engine)),
            'allow_link_to_analysis' => new PHPTalBoolean(true),
            'chunk_completion_undoable' => new PHPTalBoolean(true),
            'comments_enabled' => new PHPTalBoolean(AppConfig::$COMMENTS_ENABLED),
            'currentPassword' => $isRevision ? ($chunkReviewStruct->review_password ?? $chunkPassword) : $chunkPassword,
            'footer_show_revise_link' => new PHPTalBoolean(!$isRevision),
            'first_job_segment' => $chunkStruct->job_first_segment,
            'id_job' => $chunkId,
            'id_project' => $projectId,
            'id_team' => $chunkStruct->getProject(new ProjectDao($this->getDatabase()))->id_team,
            'isCJK' => new PHPTalBoolean(CatUtils::isCJK($chunkStruct->source)),
            'isGDriveProject' => new PHPTalBoolean((new ProjectDao($this->getDatabase()))->isGDriveProject($chunkStruct->id_project)),
            'isOpenAiEnabled' => new PHPTalBoolean(!empty(AppConfig::$OPENAI_API_KEY)),
            'isReview' => new PHPTalBoolean($isRevision),
            'isSourceRTL' => new PHPTalBoolean(Languages::getInstance()->isRTL($chunkStruct->source)),
            'isTargetRTL' => new PHPTalBoolean(Languages::getInstance()->isRTL($chunkStruct->target)),
            'ownerIsMe' => new PHPTalBoolean($jobOwnership['jobOwnerIsMe']),
            'job_is_splitted' => new PHPTalBoolean($chunkStruct->isSplit(new JobDao($this->getDatabase()))),
            'lqa_nested_categories' => new PHPTalMap($model ? $model->getSerializedCategories(new CategoryDao($this->getDatabase())) : []),
            'lqa_flat_categories' => new PHPTalMap($model ? $this->getCategoriesAsJson($model) : []),
            'maxFileSize' => AppConfig::$MAX_UPLOAD_FILE_SIZE,
            'maxTMXFileSize' => AppConfig::$MAX_UPLOAD_TMX_FILE_SIZE,
            'mt_enabled' => new PHPTalBoolean((bool)$chunkStruct->id_mt_engine),
            'not_empty_default_tm_key' => new PHPTalBoolean(!empty(AppConfig::$DEFAULT_TM_KEY)),
            'overall_quality_class' => $this->overallQualityClass($chunkReviewStruct),
            'pageTitle' => $this->buildPageTitle($revisionNumber, $chunkStruct),
            'password' => $chunkPassword,
            'project' => $chunkStruct->getProject(new ProjectDao($this->getDatabase())),
            'project_name' => Utils::friendlySlug($chunkStruct->getProject(new ProjectDao($this->getDatabase()))->name),
            'quality_report_href' => AppConfig::$BASEURL . "revise-summary/$chunkId-$chunkPassword",
            'review_extended' => new PHPTalBoolean(true),
            // The translate page publishes the first revision's password: that is the revise link its
            // footer offers, and the only phase a translator is entitled to reach.
            'review_password' => $isRevision ? ($chunkReviewStruct->review_password ?? $chunkPassword) : ((new ChunkReviewDao($this->getDatabase()))->findChunkReviewsForSourcePage(
                    $chunkStruct,
                    SourcePages::SOURCE_PAGE_REVISION
                )[0]->review_password ?? $chunkPassword),
            'revisionNumber' => $revisionNumber,
            'public_tm_penalty' => $public_tm_penalty->value ?? '',
            'searchable_statuses' => new PHPTalMap($this->searchableStatuses()),
            'secondRevisionsCount' => count(
                array_filter(
                    (new ChunkReviewDao($this->getDatabase()))->findByProjectId($projectId),
                    function (ChunkReviewStruct $chunkReviewStruct) use ($chunkStruct) {
                        return $chunkReviewStruct->id_job == $chunkStruct->id && $chunkReviewStruct->source_page > SourcePages::SOURCE_PAGE_REVISION;
                    }
                )
            ),
            'segmentFilterEnabled' => new PHPTalBoolean(true),
            // Constants the template used to spell out as literals.
            'alternativesEnabled' => new PHPTalBoolean(true),
            'is_cattool' => new PHPTalBoolean(true),
            'offlineModeEnabled' => new PHPTalBoolean(true),
            'splitSegmentEnabled' => new PHPTalBoolean(true),
            'segmentQACheckInterval' => CatUtils::isCJK($chunkStruct->target) ? 3000 * (AppConfig::$SEGMENT_QA_CHECK_INTERVAL) : 1000 * (AppConfig::$SEGMENT_QA_CHECK_INTERVAL),
            'show_tag_projection' => new PHPTalBoolean(true),
            'socket_base_url' => AppConfig::$SOCKET_BASE_URL,
            'source_code' => $chunkStruct->source,
            // The page has always carried the same language code under both names.
            'source_rfc' => $chunkStruct->source,
            'source_page' => $sourcePage,
            'status_labels' => new PHPTalMap([
                    TranslationStatus::STATUS_NEW => 'new',
                    TranslationStatus::STATUS_DRAFT => 'Draft',
                    TranslationStatus::STATUS_TRANSLATED => 'Translated',
                    TranslationStatus::STATUS_APPROVED => 'Approved',
                    TranslationStatus::STATUS_APPROVED2 => 'Revised'
                ]
            ),
            'tag_projection_languages' => new PHPTalMap(LexiQaAndTagProjectionLanguages::$tagProjectionAllowedLanguages),
            'targetIsCJK' => new PHPTalBoolean(CatUtils::isCJK($chunkStruct->target)),
            'target_code' => $chunkStruct->target,
            'target_rfc' => $chunkStruct->target,
            // The team name is user supplied and lands inside an inline <script>, where
            // PHPTAL emits interpolations verbatim. PHPTalString renders it as its own
            // quoted JSON literal so it cannot close the literal or the script element.
            'team_name' => new PHPTalString($jobOwnership['team']->name ?? ''),
            'tms_enabled' => new PHPTalBoolean((bool)$chunkStruct->id_tms),
            'intento_providers' => new PHPTalMap(Intento::getProviderList()),
            'translation_matches_enabled' => new PHPTalBoolean(true),
            'warningPollingInterval' => 1000 * (AppConfig::$WARNING_POLLING_INTERVAL),
            'word_count_type' => (new \Model\Projects\MetadataDao($this->getDatabase()))
                    ->setCacheTTL(3600)
                    ->getValue((int)$project->id, ProjectsMetadataMarshaller::WORD_COUNT_TYPE_KEY->value)
                ?? ProjectsMetadataMarshaller::WORD_COUNT_EQUIVALENT->value,
            'analysis_enabled' => new PHPTalBoolean(AppConfig::$VOLUME_ANALYSIS_ENABLED),
            'get_public_matches' => new PHPTalBoolean(!$chunkStruct->only_private_tm),

            'brPlaceholdEnabled' => new PHPTalBoolean(true),
            'lfPlaceholder' => CatUtils::lfPlaceholder,
            'crPlaceholder' => CatUtils::crPlaceholder,

            'tabPlaceholder' => CatUtils::tabPlaceholder,

            'nbspPlaceholder' => CatUtils::nbspPlaceholder,

        ]);

        // Set unconditionally. The template used to supply the unlicensed defaults itself
        // (`${lexiqa_languages || string:[]}` and friends); now that the page is built from the
        // variables the view holds, a variable left unset is a key the page never receives, and
        // lxq.main.js reads lexiqa_languages before it consults the licence.
        $licensed = (bool)AppConfig::$LXQ_LICENSE;
        $this->addParamsToView([
                'lxq_license' => $licensed ? AppConfig::$LXQ_LICENSE : '',
                'lxq_partnerid' => $licensed ? AppConfig::$LXQ_PARTNERID : '',
                'lexiqa_languages' => new PHPTalMap($licensed ? LexiQaAndTagProjectionLanguages::$lexiQaAllowedLanguages : []),
                'lexiqaServer' => $licensed ? AppConfig::$LXQ_SERVER : '',
            ]
        );

        // reset the feature set and load only the features for the current project (plus the autoloaded ones)
        $this->featureSet->loadForProject($chunkStruct->getProject(new ProjectDao($this->getDatabase())));
        $appendInitialTemplateVarsEvent = new AppendInitialTemplateVarsEvent($this->featureSet->getCodes());
        $this->featureSet->dispatch($appendInitialTemplateVarsEvent);
        $this->addParamsToView([
            'project_plugins' => new PHPTalMap($appendInitialTemplateVarsEvent->getCodes()),
        ]);

        $this->featureSet->appendDecorators(
            'CatDecorator',
            $this,
            $this->view,
            new CatDecoratorArguments(
                $chunkStruct,
                $isRevision,
                (new CatUtils($this->getDatabase()))->getWStructFromJobArray($chunkStruct, $chunkStruct->getProject(new ProjectDao($this->getDatabase()))),
                $chunkReviewStruct
            )
        );

        $this->_saveActivity($chunkId, $projectId, $isRevision);

        $this->render();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    protected function getActiveEngine(int $mt_engine_id): array
    {
        $engine = new EngineDAO($this->getDatabase());
        $engineQuery = new EngineStruct();
        $engineQuery->id = $mt_engine_id;
        $active_mt_engine = $engine->setCacheTTL(60 * 10)->read($engineQuery);

        if (!empty($active_mt_engine)) {
            return $active_mt_engine[0]->arrayRepresentation();
        }

        return [];
    }

    /**
     * @throws Exception
     */
    private function notFound(): void
    {
        $this->setView('job_not_found.html', ["support_mail" => AppConfig::$SUPPORT_MAIL], 404);
        $this->render();
    }

    /**
     * @param array{team: ?\Model\Teams\TeamStruct, owner_email: string, jobOwnerIsMe: bool} $jobOwnership
     *
     * @throws Exception
     */
    private function cancelled(array $jobOwnership): void
    {
        $this->setView('job_cancelled.html', [
            "support_mail" => AppConfig::$SUPPORT_MAIL,
            "owner_email" => $jobOwnership['owner_email'],
        ]);
        $this->render();
    }

    /**
     * @param array{team: ?\Model\Teams\TeamStruct, owner_email: string, jobOwnerIsMe: bool} $jobOwnership
     *
     * @throws Exception
     */
    private function archived(int $job_id, string $password, array $jobOwnership): void
    {
        $this->setView('job_archived.html', [
            "support_mail" => AppConfig::$SUPPORT_MAIL,
            "owner_email" => $jobOwnership['owner_email'],
            "jid" => $job_id,
            "password" => $password,
            "jobOwnerIsMe" => $jobOwnership['jobOwnerIsMe']
        ]);

        $this->render();
    }

    /**
     * @return array{team: ?\Model\Teams\TeamStruct, owner_email: string, jobOwnerIsMe: bool}
     *
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Exception
     */
    private function findOwnerEmailAndTeam(ProjectStruct $project): array
    {
        $ownerMail = AppConfig::$SUPPORT_MAIL;
        $jobOwnerIsMe = false;

        $team = $project->id_team !== null ? (new TeamDao($this->getDatabase()))->findById($project->id_team) : null;

        if (!empty($team)) {
            $teamModel = new TeamModel($team, new UserDao($this->getDatabase()), new TeamDao($this->getDatabase()));
            $teamModel->updateMembersProjectsCount();
            $membersIdList = [];
            $members = $team->getMembers();
            if ($team->type == Teams::PERSONAL) {
                $firstMember = $members[0] ?? null;
                if ($firstMember !== null) {
                    $ownerMail = $firstMember->getUser(new UserDao($this->getDatabase()))->getEmail(
                    ) ?? AppConfig::$SUPPORT_MAIL;
                }
            } else {
                $idAssignee = $project->id_assignee ?? 0;
                $assignee = (new UserDao($this->getDatabase()))->setCacheTTL(60 * 60 * 24)->getByUid((int)$idAssignee);

                if ($assignee) {
                    $ownerMail = $assignee->getEmail() ?? AppConfig::$SUPPORT_MAIL;
                } else {
                    $ownerMail = AppConfig::$SUPPORT_MAIL;
                }

                $membersIdList = array_map(function (MembershipStruct $memberStruct) {
                    return $memberStruct->uid;
                }, $members);
            }

            if ($this->user->email == $ownerMail || in_array($this->user->uid, $membersIdList)) {
                $jobOwnerIsMe = true;
            }
        }

        return [
            'team' => $team,
            'owner_email' => $ownerMail,
            'jobOwnerIsMe' => $jobOwnerIsMe,
        ];
    }

    /**
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    protected function _saveActivity(int $job_id, int $project_id, bool $isRevision): void
    {
        $action = $isRevision ? ActivityLogStruct::ACCESS_REVISE_PAGE : ActivityLogStruct::ACCESS_TRANSLATE_PAGE;

        $activity = new ActivityLogStruct();
        $activity->id_job = $job_id;
        $activity->id_project = $project_id;
        $activity->action = $action;
        $activity->ip = Utils::getRealIpAddr();
        $activity->uid = $this->user->uid;
        $activity->event_date = date('Y-m-d H:i:s');
        Activity::save($activity);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function searchableStatuses(): array
    {
        /** @var list<string> $statuses */
        $statuses = array_merge(
            TranslationStatus::$INITIAL_STATUSES,
            TranslationStatus::$TRANSLATION_STATUSES,
            [
                TranslationStatus::STATUS_APPROVED,
            ]
        );

        return array_map(function (string $item) {
            return ['value' => $item, 'label' => $item];
        }, $statuses);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws \PDOException
     */
    private function getCategoriesAsJson(ModelStruct $model): array
    {
        $categories = $model->getCategories(new CategoryDao($this->getDatabase()));
        $out = [];

        foreach ($categories as $category) {
            $out[] = $category->toArrayWithJsonDecoded();
        }

        return $out;
    }

    /**
     * is_pass is nullable and NULL means "no verdict" — a project with no LQA model never has one
     * computed. Rendering NULL as 'fail' told the reviewer their work had failed a check that was
     * never run, so an absent verdict renders as no class at all, matching QualitySummary.
     */
    private function overallQualityClass(?ChunkReviewStruct $chunkReviewStruct): string
    {
        if ($chunkReviewStruct === null || $chunkReviewStruct->is_pass === null) {
            return '';
        }

        return $chunkReviewStruct->is_pass ? 'excellent' : 'fail';
    }

    /**
     * @param ?int $revisionNumber
     * @param JobStruct $jobStruct
     *
     * @return string
     * @throws ReflectionException
     */
    protected function buildPageTitle(?int $revisionNumber, JobStruct $jobStruct): string
    {
        if ($revisionNumber > 1) {
            $pageTitle = 'Revise ' . $revisionNumber . ' - ';
        } elseif ($revisionNumber === 1) {
            $pageTitle = 'Revise - ';
        } else {
            $pageTitle = 'Translate - ';
        }

        return $pageTitle . $jobStruct->getProject(new ProjectDao($this->getDatabase()))->name . ' - ' . $jobStruct->id;
    }

}
