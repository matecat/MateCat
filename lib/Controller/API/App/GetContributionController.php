<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\APISourcePageGuesserTrait;
use Exception;
use InvalidArgumentException;
use Matecat\SubFiltering\MateCatFilter;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\Hook\Event\Filter\RewriteContributionContextsEvent;
use Model\Files\FilesPartsDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentOriginalDataDao;
use Model\Users\UserDao;
use ReflectionException;
use TypeError;
use Utils\Contribution\Get;
use Utils\Contribution\GetContributionRequest;
use Utils\Engines\Lara;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\Filter;

class GetContributionController extends KleinController
{

    use APISourcePageGuesserTrait;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     * @throws TypeError
     */
    public function get(): void
    {
        $request = $this->validateTheRequest();

        $id_job = $request['id_job'];
        $id_segment = $request['id_segment'];
        $password = $request['password'];

        $jobStruct = (new JobDao())->getByIdAndPasswordOrFail($id_job, $password);
        $dataRefMap = SegmentOriginalDataDao::getSegmentDataRefMap($id_segment);

        $projectStruct = $jobStruct->getProject();
        $this->featureSet->loadForProject($projectStruct);

        $id_client = $request['id_client'];
        $num_results = $request['num_results'];
        $received_password = $request['received_password'];
        $concordance_search = $request['concordance_search'];
        $switch_languages = $request['switch_languages'];
        $cross_language = $request['cross_language'];
        $reasoning = $request['reasoning'];

        // try to get from metadata if Lara style is empty of fallback to the default
        if (empty($request['lara_style'])) {
            $lara_style = $projectStruct->getMetadataValue('lara_style') ?? Lara::DEFAULT_STYLE;
        } else {
            $lara_style = $request['lara_style'];
        }

        if (empty($num_results)) {
            $num_results = AppConfig::$DEFAULT_NUM_RESULTS_FROM_TM;
        }

        $contributionRequest = new GetContributionRequest();
        $jobId = $jobStruct->id ?? throw new TypeError('Job ID must not be null');
        $jobPassword = $jobStruct->password ?? throw new TypeError('Job password must not be null');
        $subfiltering_handlers = (new MetadataDao())->getSubfilteringCustomHandlers($jobId, $jobPassword);
        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance($this->featureSet, $jobStruct->source, $jobStruct->target, $dataRefMap, $subfiltering_handlers);

        $context_list_before = [];
        $context_list_after = [];
        if (!$concordance_search) {
            $context_list_before = array_values(array_map(function (string $context) use ($Filter) {
                return $Filter->fromLayer2ToLayer1($context);
            }, $request['context_list_before'] ?? []));

            $context_list_after = array_values(array_map(function (string $context) use ($Filter) {
                return $Filter->fromLayer2ToLayer1($context);
            }, $request['context_list_after'] ?? []));

            $this->rewriteContributionContexts($request, $Filter);

            $mtEvaluation = $projectStruct->getMetadataValue(ProjectsMetadataMarshaller::MT_EVALUATION->value);
            if ($mtEvaluation === null) {
                //TODO REMOVE after a reasonable amount of time, this is for back compatibility, previously the mt_evaluation flag was on jobs metadata
                $mtEvaluation = (new MetadataDao())->get(
                    $jobId,
                    $received_password,
                    ProjectsMetadataMarshaller::MT_EVALUATION->value,
                    60 * 60
                )?->value;
            }
            $contributionRequest->mt_evaluation = (bool)$mtEvaluation;
        }

        $file = (new FilesPartsDao())->getBySegmentId($id_segment);
        $owner = (new UserDao())->getProjectOwner($id_job) ?? throw new TypeError('Project owner not found');

        $contributionRequest->id_file = $file?->id;
        $contributionRequest->id_job = $id_job;
        $contributionRequest->password = $received_password;
        $contributionRequest->dataRefMap = $dataRefMap;
        $contributionRequest->contexts = [
            'context_before' => $request['context_before'] ?? null,
            'segment' => $request['text'],
            'context_after' => $request['context_after'] ?? null
        ];

        $contributionRequest->context_list_before = $context_list_before;
        $contributionRequest->context_list_after = $context_list_after;

        $contributionRequest->translation = $request['translation'] ?? null; // in the case of Lara Think

        $contributionRequest->setUser($owner);
        $contributionRequest->setJobStruct($jobStruct);
        $contributionRequest->setProjectStruct($projectStruct);
        $contributionRequest->lara_style = $lara_style;
        $contributionRequest->segmentId = $id_segment;
        $contributionRequest->id_client = $id_client;
        $contributionRequest->concordanceSearch = $concordance_search;
        $contributionRequest->fromTarget = $switch_languages;
        $contributionRequest->resultNum = $num_results;
        $contributionRequest->crossLangTargets = array_values($this->getCrossLanguages($cross_language));
        $contributionRequest->mt_quality_value_in_editor = (int)($projectStruct->getMetadataValue(ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value) ?? 86);
        $contributionRequest->mt_qe_workflow_enabled = (bool)$projectStruct->getMetadataValue(ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value);

        $mtQeParams = $projectStruct->getMetadataValue(ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value);
        $contributionRequest->mt_qe_workflow_parameters = $mtQeParams instanceof MTQEWorkflowParams ? $mtQeParams->toArray() : null;
        $contributionRequest->subfiltering_handlers = $subfiltering_handlers !== null ? array_values($subfiltering_handlers) : null;

        if ($this->isRevision()) {
            $contributionRequest->userRole = Filter::ROLE_REVISOR;
        } else {
            $contributionRequest->userRole = Filter::ROLE_TRANSLATOR;
        }

        $jobsMetadataDao = new MetadataDao();
        $dialect_strict = $jobsMetadataDao->get($jobId, $jobPassword, JobsMetadataMarshaller::DIALECT_STRICT->value, 10 * 60);
        $mt_evaluation = $jobsMetadataDao->get($jobId, $jobPassword, 'mt_evaluation', 10 * 60);
        $public_tm_penalty = $jobsMetadataDao->get($jobId, $jobPassword, JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, 10 * 60);

        if ($public_tm_penalty !== null) {
            $contributionRequest->public_tm_penalty = (int)$public_tm_penalty->value;
        }

        if ($dialect_strict !== null) {
            $contributionRequest->dialect_strict = $dialect_strict->value == 1;
        }
        if ($reasoning !== null) {
            $contributionRequest->reasoning = $reasoning;
        }

        if ($mt_evaluation !== null) {
            $contributionRequest->mt_evaluation = $mt_evaluation->value == 1;
        }

        $tm_prioritization = $jobsMetadataDao->get($jobId, $jobPassword, JobsMetadataMarshaller::TM_PRIORITIZATION->value, 10 * 60);

        if ($tm_prioritization !== null) {
            $contributionRequest->tm_prioritization = $tm_prioritization->value == 1;
        }

        if ($contributionRequest->concordanceSearch) {
            $contributionRequest->resultNum = 10;
        }

        $this->dispatchContribution($contributionRequest);

        $this->response->json([
            'errors' => [],
            'data' => [
                "message" => "OK",
                "id_client" => $id_client,
                "request" => [
                    'session_id' => $contributionRequest->getSessionId(),
                    'id_file' => (int)$contributionRequest->id_file,
                    'id_job' => (int)$contributionRequest->id_job,
                    'password' => $contributionRequest->password,
                    'contexts' => $contributionRequest->contexts,
                    'id_client' => $contributionRequest->id_client,
                    'userRole' => $contributionRequest->userRole,
                    'tm_prioritization' => $contributionRequest->tm_prioritization,
                    'mt_evaluation' => $contributionRequest->mt_evaluation,
                    'crossLangTargets' => $contributionRequest->crossLangTargets,
                    'fromTarget' => $contributionRequest->fromTarget,
                    'dialect_strict' => $contributionRequest->dialect_strict,
                    'segmentId' => $contributionRequest->segmentId ? (string)$contributionRequest->segmentId : null,
                    'resultNum' => (int)$contributionRequest->resultNum,
                    'lara_style' => $contributionRequest->lara_style,
                    'reasoning' => $contributionRequest->reasoning,
                    'concordanceSearch' => $contributionRequest->concordanceSearch,
                ]
            ]
        ]);
    }

    /**
     * @throws Exception
     */
    protected function dispatchContribution(GetContributionRequest $contributionRequest): void
    {
        Get::contribution($contributionRequest);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws TypeError
     */
    private function validateTheRequest(): array
    {
        $id_client = filter_var($this->request->param('id_client'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]) ?: '';
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT, ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_SCALAR]);
        $id_segment = filter_var($this->request->param('id_segment'), FILTER_SANITIZE_NUMBER_INT);  // FILTER_SANITIZE_NUMBER_INT leaves untouched segments id with the split flag. Ex: 123-1
        $num_results = filter_var($this->request->param('num_results'), FILTER_SANITIZE_NUMBER_INT, [
                'filter' => FILTER_VALIDATE_INT,
                'options' => [
                    'min_range' => 0,

                    // we don't want mt again, we already have Lara Think
                    // decrease the number of requested matches
                    'max_range' => AppConfig::$DEFAULT_NUM_RESULTS_FROM_TM - ($this->request->param('translation') ? 1 : 0)
                ],
                'flags' => FILTER_REQUIRE_SCALAR
            ]
        );
        $text = (string)filter_var($this->request->param('text'), FILTER_UNSAFE_RAW);
        $translation = (string)filter_var($this->request->param('translation'), FILTER_UNSAFE_RAW); // in the case of Lara Think
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]) ?: '';
        $received_password = filter_var($this->request->param('current_password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]) ?: '';
        $concordance_search = filter_var($this->request->param('is_concordance'), FILTER_VALIDATE_BOOLEAN);
        $reasoning = filter_var($this->request->param('reasoning'), FILTER_VALIDATE_BOOLEAN);
        $switch_languages = filter_var($this->request->param('from_target'), FILTER_VALIDATE_BOOLEAN);
        $context_before = filter_var($this->request->param('context_before'), FILTER_UNSAFE_RAW) ?: '';
        $context_after = filter_var($this->request->param('context_after'), FILTER_UNSAFE_RAW) ?: '';
        $context_list_before = filter_var($this->request->param('context_list_before'), FILTER_UNSAFE_RAW) ?: '';
        $context_list_after = filter_var($this->request->param('context_list_after'), FILTER_UNSAFE_RAW) ?: '';
        $id_before = filter_var($this->request->param('id_before'), FILTER_SANITIZE_NUMBER_INT);
        $id_after = filter_var($this->request->param('id_after'), FILTER_SANITIZE_NUMBER_INT);
        $cross_language = filter_var($this->request->param('cross_language'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FORCE_ARRAY]);
        $text = trim($text);
        $translation = trim($translation);
        $lara_style = filter_var($this->request->param('lara_style'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]) ?: '';

        if (!$concordance_search) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            //because the segment can be optional
            if (empty($id_segment)) {
                throw new InvalidArgumentException("missing id_segment", -1);
            }
        }

        // Allowing "0" as text
        if (empty($text) and $text != "0") {
            throw new InvalidArgumentException("missing text", -2);
        }

        if (empty($id_job)) {
            throw new InvalidArgumentException("missing id job", -3);
        }

        if (empty($password)) {
            throw new InvalidArgumentException("missing job password", -4);
        }

        if (empty($id_client)) {
            throw new InvalidArgumentException("missing id_client", -5);
        }

        // validate Lara style
        if (!empty($lara_style)) {
            $lara_style = Lara::validateLaraStyle($lara_style);
        }

        $this->id_job = (int)$id_job;
        $this->request_password = $received_password;

        return [
            'id_client' => $id_client,
            'id_job' => (int)$id_job,
            'id_segment' => $id_segment,
            'num_results' => $num_results,
            'text' => $text,
            'translation' => $translation,
            'password' => $password,
            'received_password' => $received_password,
            'concordance_search' => $concordance_search,
            'switch_languages' => $switch_languages,
            'context_before' => $context_before,
            'context_after' => $context_after,
            'id_before' => $id_before,
            'id_after' => $id_after,
            'cross_language' => $cross_language,
            'lara_style' => $lara_style,
            'reasoning' => $reasoning,
            'context_list_after' => json_decode($context_list_after, true),
            'context_list_before' => json_decode($context_list_before, true),
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param MateCatFilter $Filter
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    private function rewriteContributionContexts(array &$request, MateCatFilter $Filter): void
    {
        //Get contexts
        $segmentsList = (new SegmentDao)->setCacheTTL(60 * 60 * 24)->getContextAndSegmentByIDs(
            [
                'id_before' => $request['id_before'],
                'id_segment' => $request['id_segment'],
                'id_after' => $request['id_after']
            ]
        );

        $rewriteContributionContextsEvent = new RewriteContributionContextsEvent($segmentsList, $request);
        $this->featureSet->dispatch($rewriteContributionContextsEvent);
        $segmentsList = $rewriteContributionContextsEvent->getSegmentsList();
        $request = $rewriteContributionContextsEvent->getRequestData();

        if ($segmentsList->id_before) {
            $request['context_before'] = $Filter->fromLayer0ToLayer1($segmentsList->id_before->segment);
        }

        if ($segmentsList->id_segment) {
            $request['text'] = $Filter->fromLayer0ToLayer1($segmentsList->id_segment->segment);
        }

        if ($segmentsList->id_after) {
            $request['context_after'] = $Filter->fromLayer0ToLayer1($segmentsList->id_after->segment);
        }
    }

    /**
     * Remove voids
     * ("en-GB," => [0 => 'en-GB'])
     *
     * @param string|array<int|string, mixed> $cross_language
     *
     * @return list<string>
     */
    private function getCrossLanguages(string|array $cross_language): array
    {
        if (is_array($cross_language) && !empty($cross_language)) {
            return explode(",", rtrim((string)$cross_language[0], ','));
        }

        return [];
    }
}
