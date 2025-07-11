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
use INIT;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamModel;
use Model\Users\UserDao;
use PHPTalBoolean;
use PHPTalMap;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ProjectOptionsSanitizer;
use ReflectionException;
use stdClass;
use Utils;
use Utils\CatUtils;
use Utils\Constants\Teams;
use Utils\Constants\TranslationStatus;
use Utils\Engines\Intento;
use Utils\Langs\Languages;

class CattoolController extends BaseKleinViewController {

    protected function afterConstruct() {
        $this->appendValidator( new ViewLoginRedirectValidator( $this ) );
    }

    protected function validateTheRequest(): array {

        $filterArgs = [
                'jid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $result                 = filter_var_array( $this->request->paramsNamed()->all(), $filterArgs );
        $this->request_password = $result[ 'password' ];
        $this->id_job           = $result[ 'jid' ];

        return $result;
    }

    /**
     * findJobByIdPasswordAndSourcePage
     *
     * Finds the current chunk by job id, password and source page. If in revision, then
     * pass the control to a filter, to allow plugin to interact with the
     * authorization process.
     *
     * Filters may restore the password to the actual password contained in
     * `jobs` table, while the request may have come with a different password
     * for access control.
     *
     * This is done to avoid the rewrite of preexisting implementations.
     *
     * @throws Exception
     */
    private function findJobByIdPasswordAndSourcePage( int $job_id, string $password, int $sourcePage, bool $isRevision ): stdClass {

        $result = [
                'chunk'             => null,
                'chunkReviewStruct' => null,
                'isRevision'        => $isRevision,
        ];

        if ( $isRevision ) {

            $chunkReviewStruct = ( new ChunkReviewDao() )->findByJobIdReviewPasswordAndSourcePage( $job_id, $password, $sourcePage );

            if ( !$chunkReviewStruct ) {
                throw new NotFoundException( 'Review record was not found' );
            }

            $result[ 'chunk' ]             = $chunkReviewStruct->getChunk();
            $result[ 'chunkReviewStruct' ] = $chunkReviewStruct;
        } else {
            $result[ 'chunk' ] = ChunkDao::getByIdAndPassword( $job_id, $password );
        }

        return (object)$result;

    }

    /**
     * @throws Exception
     */
    public function renderView() {

        $chunkAndPasswords = new stdClass();
        $request           = $this->validateTheRequest();
        $isRevision        = CatUtils::getIsRevisionFromRequestUri();
        $revisionNumber    = null;

        try {
            $chunkAndPasswords = $this->findJobByIdPasswordAndSourcePage( $request[ 'jid' ], $request[ 'password' ], Utils::getSourcePage(), $isRevision );
            $revisionNumber    = ReviewUtils::sourcePageToRevisionNumber( $chunkAndPasswords->chunkReviewStruct ? $chunkAndPasswords->chunkReviewStruct->source_page : null );
        } catch ( NotFoundException $e ) {
            $this->notFound();
        }

        /** @var $chunkStruct JobStruct */
        $chunkStruct = $chunkAndPasswords->chunk;

        /** @var  $chunkReviewStruct ?ChunkReviewStruct */
        $chunkReviewStruct = $chunkAndPasswords->chunkReviewStruct;

        $jobOwnership = $this->findOwnerEmailAndTeam( $chunkStruct->getProject() );

        if ( $chunkStruct->isCanceled() ) {
            $this->cancelled( $jobOwnership );
        }

        if ( $chunkStruct->isArchived() ) {
            $this->archived(
                    $request[ 'jid' ],
                    $isRevision ? $chunkReviewStruct->review_password : $chunkReviewStruct->password,
                    $jobOwnership
            );
        }

        if ( $chunkStruct->isDeleted() ) {
            $this->notFound();
        }

        $model = $chunkStruct->getProject()->getLqaModel();

        $this->setView( "index.html", [
                'active_engine'                         => new PHPTalMap( $this->getActiveEngine( $chunkStruct->id_mt_engine ) ),
                'allow_link_to_analysis'                => new PHPTalBoolean( true ),
                'chunk_completion_undoable'             => new PHPTalBoolean( true ),
                'comments_enabled'                      => new PHPTalBoolean( INIT::$COMMENTS_ENABLED ),
                'currentPassword'                       => $isRevision ? $chunkReviewStruct->review_password : $chunkStruct->password,
                'footer_show_revise_link'               => new PHPTalBoolean( !$isRevision ),
                'first_job_segment'                     => $chunkStruct->job_first_segment,
                'id_job'                                => $chunkStruct->id,
                'id_project'                            => $chunkStruct->getProject()->id,
                'id_team'                               => $chunkStruct->getProject()->id_team,
                'isCJK'                                 => new PHPTalBoolean( CatUtils::isCJK( $chunkStruct->source ) ),
                'isGDriveProject'                       => new PHPTalBoolean( ProjectDao::isGDriveProject( $chunkStruct->id_project ) ),
                'isOpenAiEnabled'                       => new PHPTalBoolean( !empty( INIT::$OPENAI_API_KEY ) ),
                'isReview'                              => new PHPTalBoolean( $isRevision ),
                'isSourceRTL'                           => new PHPTalBoolean( Languages::getInstance()->isRTL( $chunkStruct->source ) ),
                'isTargetRTL'                           => new PHPTalBoolean( Languages::getInstance()->isRTL( $chunkStruct->target ) ),
                'jobOwnerIsMe'                          => new PHPTalBoolean( $jobOwnership[ 'jobOwnerIsMe' ] ),
                'job_is_splitted'                       => new PHPTalBoolean( $chunkStruct->isSplitted() ),
                'lqa_categories'                        => new PHPTalMap( $model ? $model->getSerializedCategories() : [] ),
                'lqa_flat_categories'                   => new PHPTalMap( $model ? $this->getCategoriesAsJson( $model ) : [] ),
                'maxFileSize'                           => INIT::$MAX_UPLOAD_FILE_SIZE,
                'maxTMXFileSize'                        => INIT::$MAX_UPLOAD_TMX_FILE_SIZE,
                'mt_enabled'                            => new PHPTalBoolean( (bool)$chunkStruct->id_mt_engine ),
                'not_empty_default_tm_key'              => new PHPTalBoolean( !empty( INIT::$DEFAULT_TM_KEY ) ),
                'overall_quality_class'                 => $chunkReviewStruct ? ( $chunkReviewStruct->is_pass ? 'excellent' : 'fail' ) : '',
                'pageTitle'                             => $this->buildPageTitle( $revisionNumber, $chunkStruct ),
                'password'                              => $chunkStruct->password,
                'project'                               => $chunkStruct->getProject(),
                'project_name'                          => $chunkStruct->getProject()->name,
                'quality_report_href'                   => INIT::$BASEURL . "revise-summary/$chunkStruct->id-$chunkStruct->password",
                'review_extended'                       => new PHPTalBoolean( true ),
                'review_password'                       => $isRevision ? $chunkReviewStruct->review_password : ( new ChunkReviewDao() )->findChunkReviewsForSourcePage( $chunkStruct, Utils::getSourcePage() + 1 )[ 0 ]->review_password,
                'revisionNumber'                        => $revisionNumber,
                'searchable_statuses'                   => new PHPTalMap( $this->searchableStatuses() ),
                'secondRevisionsCount'                  => count( ChunkReviewDao::findByProjectId( $chunkStruct->getProject()->id ) ),
                'segmentFilterEnabled'                  => new PHPTalBoolean( true ),
                'segmentQACheckInterval'                => CatUtils::isCJK( $chunkStruct->target ) ? 3000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL ) : 1000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL ),
                'show_tag_projection'                   => new PHPTalBoolean( true ),
                'socket_base_url'                       => INIT::$SOCKET_BASE_URL,
                'source_code'                           => $chunkStruct->source,
                'source_page'                           => Utils::getSourcePage(),
                'status_labels'                         => new PHPTalMap( [
                                TranslationStatus::STATUS_NEW        => 'new',
                                TranslationStatus::STATUS_DRAFT      => 'Draft',
                                TranslationStatus::STATUS_TRANSLATED => 'Translated',
                                TranslationStatus::STATUS_APPROVED   => 'Approved',
                                TranslationStatus::STATUS_APPROVED2  => 'Revised'
                        ]
                ),
                'tag_projection_languages'              => new PHPTalMap( ProjectOptionsSanitizer::$tag_projection_allowed_languages ),
                'targetIsCJK'                           => new PHPTalBoolean( CatUtils::isCJK( $chunkStruct->target ) ),
                'target_code'                           => $chunkStruct->target,
                'team_name'                             => $jobOwnership[ 'team' ]->name,
                'tms_enabled'                           => new PHPTalBoolean( (bool)$chunkStruct->id_tms ),
                'translation_engines_intento_providers' => new PHPTalMap( Intento::getProviderList() ),
                'translation_matches_enabled'           => new PHPTalBoolean( true ),
                'warningPollingInterval'                => 1000 * ( INIT::$WARNING_POLLING_INTERVAL ),
                'word_count_type'                       => $chunkStruct->getProject()->getWordCountType(),


                'brPlaceholdEnabled'   => new PHPTalBoolean( true ),
                'lfPlaceholder'        => CatUtils::lfPlaceholder,
                'crPlaceholder'        => CatUtils::crPlaceholder,
                'crlfPlaceholder'      => CatUtils::crlfPlaceholder,
                'lfPlaceholderClass'   => CatUtils::lfPlaceholderClass,
                'crPlaceholderClass'   => CatUtils::crPlaceholderClass,
                'crlfPlaceholderClass' => CatUtils::crlfPlaceholderClass,
                'lfPlaceholderRegex'   => CatUtils::lfPlaceholderRegex,
                'crPlaceholderRegex'   => CatUtils::crPlaceholderRegex,
                'crlfPlaceholderRegex' => CatUtils::crlfPlaceholderRegex,

                'tabPlaceholder'      => CatUtils::tabPlaceholder,
                'tabPlaceholderClass' => CatUtils::tabPlaceholderClass,
                'tabPlaceholderRegex' => CatUtils::tabPlaceholderRegex,

                'nbspPlaceholder'      => CatUtils::nbspPlaceholder,
                'nbspPlaceholderClass' => CatUtils::nbspPlaceholderClass,
                'nbspPlaceholderRegex' => CatUtils::nbspPlaceholderRegex,

        ] );

        if ( INIT::$LXQ_LICENSE ) {
            $this->addParamsToView( [
                            'lxq_license'      => INIT::$LXQ_LICENSE,
                            'lxq_partnerid'    => INIT::$LXQ_PARTNERID,
                            'lexiqa_languages' => new PHPTalMap( ProjectOptionsSanitizer::$lexiQA_allowed_languages ),
                            'lexiqaServer'     => INIT::$LXQ_SERVER,
                    ]
            );
        }

        // reset the feature set and load only the features for the current project (plus the autoloaded ones)
        $this->featureSet->loadForProject( $chunkStruct->getProject() );
        $this->addParamsToView( [
                'project_plugins' => new PHPTalMap( $this->featureSet->filter( 'appendInitialTemplateVars', $this->featureSet->getCodes() ) ),
        ] );

        $this->featureSet->appendDecorators(
                'CatDecorator',
                $this,
                $this->view,
                new CatDecoratorArguments(
                        $chunkStruct,
                        $isRevision,
                        CatUtils::getWStructFromJobArray( $chunkStruct, $chunkStruct->getProject() ),
                        $chunkReviewStruct
                )
        );

        $this->_saveActivity( $chunkStruct->id, $chunkStruct->getProject()->id, $isRevision );

        $this->render();

    }

    /**
     * @throws Exception
     */
    protected function getActiveEngine( int $mt_engine_id ): array {
        $engine           = new EngineDAO();
        $engineQuery      = new EngineStruct();
        $engineQuery->id  = $mt_engine_id;
        $active_mt_engine = $engine->setCacheTTL( 60 * 10 )->read( $engineQuery );
        if ( !empty( $active_mt_engine ) ) {
            return [
                    "id"          => $active_mt_engine[ 0 ]->id,
                    "name"        => $active_mt_engine[ 0 ]->name,
                    "type"        => $active_mt_engine[ 0 ]->type,
                    "description" => $active_mt_engine[ 0 ]->description,
            ];
        }

        return [];
    }

    /**
     * @throws Exception
     */
    private function notFound() {
        $this->setView( 'job_not_found.html', [ "support_mail" => INIT::$SUPPORT_MAIL ], 404 );
        $this->render();
    }

    /**
     * @throws Exception
     */
    private function cancelled( array $jobOwnership ) {
        $this->setView( 'job_cancelled.html', [
                "support_mail" => INIT::$SUPPORT_MAIL,
                "owner_email"  => $jobOwnership[ 'owner_email' ],
        ] );
        $this->render();
    }

    /**
     * @throws Exception
     */
    private function archived( int $job_id, string $password, array $jobOwnership ) {
        $this->setView( 'job_archived.html', [
                "support_mail" => INIT::$SUPPORT_MAIL,
                "owner_email"  => $jobOwnership[ 'owner_email' ],
                "jid"          => $job_id,
                "password"     => $password,
                "jobOwnerIsMe" => $jobOwnership[ 'jobOwnerIsMe' ]
        ] );

        $this->render();
    }

    /**
     * @throws ReflectionException
     */
    private function findOwnerEmailAndTeam( ProjectStruct $project ): array {

        $ownerMail    = INIT::$SUPPORT_MAIL;
        $jobOwnerIsMe = false;

        $team = $project->getTeam();

        if ( !empty( $team ) ) {

            $teamModel = new TeamModel( $team );
            $teamModel->updateMembersProjectsCount();
            $membersIdList = [];
            if ( $team->type == Teams::PERSONAL ) {
                $ownerMail = $team->getMembers()[ 0 ]->getUser()->getEmail();
            } else {
                $assignee = ( new UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $project->id_assignee );

                if ( $assignee ) {
                    $ownerMail = $assignee->getEmail();
                } else {
                    $ownerMail = INIT::$SUPPORT_MAIL;
                }

                $membersIdList = array_map( function ( $memberStruct ) {
                    /**
                     * @var $memberStruct MembershipStruct
                     */
                    return $memberStruct->uid;
                }, $team->getMembers() );

            }

            if ( $this->user->email == $ownerMail || in_array( $this->user->uid, $membersIdList ) ) {
                $jobOwnerIsMe = true;
            }

        }

        return [
                'team'         => $team,
                'owner_email'  => $ownerMail,
                'jobOwnerIsMe' => $jobOwnerIsMe,
        ];

    }

    protected function _saveActivity( int $job_id, int $project_id, bool $isRevision ) {

        if ( $isRevision ) {
            $action = ActivityLogStruct::ACCESS_REVISE_PAGE;
        } else {
            $action = ActivityLogStruct::ACCESS_TRANSLATE_PAGE;
        }

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $job_id;
        $activity->id_project = $project_id;
        $activity->action     = $action;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y - m - d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * @return array
     */
    private function searchableStatuses(): array {
        $statuses = array_merge(
                TranslationStatus::$INITIAL_STATUSES,
                TranslationStatus::$TRANSLATION_STATUSES,
                [
                        TranslationStatus::STATUS_APPROVED,
                ]
        );

        return array_map( function ( $item ) {
            return [ 'value' => $item, 'label' => $item ];
        }, $statuses );
    }

    /**
     * @param ModelStruct $model
     *
     * @return array
     */
    private function getCategoriesAsJson( ModelStruct $model ): array {
        $categories = $model->getCategories();
        $out        = [];

        foreach ( $categories as $category ) {
            $out[] = $category->toArrayWithJsonDecoded();
        }

        return $out;
    }

    /**
     * @param ?int      $revisionNumber
     * @param JobStruct $jobStruct
     *
     * @return string
     */
    protected function buildPageTitle( ?int $revisionNumber, JobStruct $jobStruct ): string {
        if ( $revisionNumber > 1 ) {
            $pageTitle = 'Revise ' . $revisionNumber . ' - ';
        } elseif ( $revisionNumber === 1 ) {
            $pageTitle = 'Revise - ';
        } else {
            $pageTitle = 'Translate - ';
        }

        return $pageTitle . $jobStruct->getProject()->name . ' - ' . $jobStruct->id;
    }

}