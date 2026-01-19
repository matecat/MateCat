<?php

namespace Plugins\Features;

use ArrayObject;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Exception;
use Klein\Klein;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureCodes;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\ModelDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\QualityReport\QualityReportModel;
use Model\ReviseFeedback\FeedbackDAO;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use ReflectionException;
use Utils\Collections\RecursiveArrayObject;
use Utils\Constants\SourcePages;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

abstract class AbstractRevisionFeature extends BaseFeature
{

    protected static array $dependencies = [
        FeatureCodes::TRANSLATION_VERSIONS
    ];

    public function __construct(BasicFeatureStruct $feature)
    {
        parent::__construct($feature);
    }

    /**
     * @param array $projectFeatures
     *
     * @return array
     * @throws Exception
     */
    public function filterCreateProjectFeatures(array $projectFeatures): array
    {
        $projectFeatures[static::FEATURE_CODE] = new BasicFeatureStruct(['feature_code' => static::FEATURE_CODE]);

        return $projectFeatures;
    }

    public static function loadRoutes(Klein $klein): void
    {
        route('/project/[:id_project]/[:password]/reviews', 'POST', ['Controller\API\V2\ReviewsController', 'createReview']);
    }

    /**
     * @param array $data
     * @param JobStruct $chunk
     *
     * @return array
     */
    public function filterGetSegmentsResult(array $data, JobStruct $chunk): array
    {
        if (empty($data['files'])) {
            // this means that there are no more segments after
            return $data;
        }

        reset($data['files']);

        $firstFile = current($data['files']);
        $lastFile = end($data['files']);
        $firstSid = $firstFile['segments'][0]['sid'];

        if (isset($lastFile['segments']) and is_array($lastFile['segments'])) {
            $lastSegment = end($lastFile['segments']);
            $lastSid = $lastSegment['sid'];

            $segment_translation_events = (new TranslationEventDao())->getLatestEventsInSegmentInterval(
                $chunk->id,
                $firstSid,
                $lastSid
            );

            $by_id_segment = [];
            foreach ($segment_translation_events as $record) {
                $by_id_segment[$record->id_segment] = $record;
            }

            foreach ($data['files'] as $file => $content) {
                foreach ($content['segments'] as $key => $segment) {
                    if (isset($by_id_segment[$segment['sid']])) {
                        $data ['files'] [$file] ['segments'] [$key] ['revision_number'] = ReviewUtils::sourcePageToRevisionNumber(
                            $by_id_segment[$segment['sid']]->source_page
                        );
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param string $password
     * @param int $id_job
     *
     * @return string
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function filter_job_password_to_review_password(string $password, int $id_job): string
    {
        $chunk_review = (new ChunkReviewDao())->findChunkReviews(new JobStruct(['id' => $id_job, 'password' => $password]))[0];

        if (!$chunk_review) {
            $chunk_review = ChunkReviewDao::findByReviewPasswordAndJobId($password, $id_job);
        }

        if (!$chunk_review) {
            throw new NotFoundException('Review record was not found');
        }

        return $chunk_review->review_password;
    }

    /**
     * Performs post-project creation tasks for the current project.
     * Evaluates if a qa model is present in the feature options.
     * If so, then try to assign the defined qa_model.
     * If not, then try to find the qa_model from the project structure.
     *
     * @param RecursiveArrayObject $projectStructure
     *
     * @throws ReflectionException
     */
    public function postProjectCreate(RecursiveArrayObject $projectStructure): void
    {
        if ($this instanceof ReviewExtended) {
            return;
        }

        $this->setQaModelFromJsonFile($projectStructure);
        $this->createChunkReviewRecords($projectStructure);
    }

    /**
     * @param JobStruct[]|ChunkReviewStruct[] $chunksArray
     * @param ProjectStruct $project
     * @param array $options
     *
     * @return array
     * @throws Exception
     */
    public function createQaChunkReviewRecords(array $chunksArray, ProjectStruct $project, array $options = []): array
    {
        $createdRecords = [];

        // expect one chunk
        if (!isset($options['source_page'])) {
            $options['source_page'] = SourcePages::SOURCE_PAGE_REVISION;
        }

        foreach ($chunksArray as $k => $chunk) {
            $data = [
                'id_project' => $project->id,
                'id_job' => $chunk->id,
                'password' => $chunk->password,
                'source_page' => $options['source_page']
            ];

            if ($k == 0 && array_key_exists('first_record_password', $options) != null) {
                $data['review_password'] = $options['first_record_password'];
            }

            $chunkReview = ChunkReviewDao::createRecord($data);
            $createdRecords[] = $chunkReview;
        }

        return $createdRecords;
    }

    /**
     * @param ArrayObject $projectStructure
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function createChunkReviewRecords(ArrayObject $projectStructure): void
    {
        $project = ProjectDao::findById($projectStructure['id_project']);
        foreach ($projectStructure['array_jobs']['job_list'] as $id_job) {
            $chunkStruct = JobDao::getById($id_job);

            $iMax = 3;

            if (isset($projectStructure['create_2_pass_review']) && $projectStructure['create_2_pass_review']) {
                $iMax = 4;
            }

            for ($i = 2; $i < $iMax; $i++) {
                $this->createQaChunkReviewRecords($chunkStruct, $project, ['source_page' => $i]);
            }
        }
    }

    /**
     * postJobSplitted
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     *
     */
    public function postJobSplitted(ArrayObject $projectStructure): void
    {
        /**
         * By definition, when running postJobSplitted callback, the job is not split.
         * So we expect to find just one record in chunk_reviews for the job.
         * If we find more than one record, it's one record for each revision.
         *
         */

        $id_job = $projectStructure['job_to_split'];
        $previousRevisionRecords = ChunkReviewDao::findByIdJob($id_job);
        $project = ProjectDao::findById($projectStructure['id_project'], 86400);

        ChunkReviewDao::deleteByJobId($id_job);

        $chunksStructArray = JobDao::getById($id_job);

        $reviews = [];
        foreach ($previousRevisionRecords as $review) {
            // check if $review belongs to a deleted job
            $chunk = JobDao::getByIdAndPassword($review->id_job, $review->password);

            if (!$chunk->isDeleted()) {
                $reviews = array_merge(
                    $reviews,
                    $this->createQaChunkReviewRecords(
                        $chunksStructArray,
                        $project,
                        [
                            'first_record_password' => $review->review_password,
                            'source_page' => $review->source_page
                        ]
                    )
                );
            }
        }

        foreach ($reviews as $review) {
            $model = new ChunkReviewModel($review);
            $model->recountAndUpdatePassFailResult($project);
        }
    }

    /**
     * postJobMerged
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param ArrayObject $projectStructure
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function postJobMerged(ArrayObject $projectStructure): void
    {
        $id_job = $projectStructure['job_to_merge'];
        $old_reviews = ChunkReviewDao::findByIdJob($id_job);
        $project = ProjectDao::findById($projectStructure['id_project'], 86400);

        $reviewGroupedData = [];

        foreach ($old_reviews as $review) {
            if (!isset($reviewGroupedData[$review->source_page])) {
                $reviewGroupedData[$review->source_page] = [
                    'first_record_password' => $review->review_password
                ];
            }
        }

        ChunkReviewDao::deleteByJobId($id_job);

        $chunksStructArray = JobDao::getById($id_job);

        $reviews = [];
        foreach ($reviewGroupedData as $source_page => $data) {
            $reviews = array_merge(
                $reviews,
                $this->createQaChunkReviewRecords(
                    $chunksStructArray,
                    $project,
                    [
                        'first_record_password' => $data['first_record_password'],
                        'source_page' => $source_page
                    ]
                )
            );
        }

        foreach ($reviews as $review) {
            $model = new ChunkReviewModel($review);
            $model->recountAndUpdatePassFailResult($project);
        }
    }

    /**
     *
     * project_completion_event_saved
     *
     * @param JobStruct $chunk
     * @param CompletionEventStruct $event
     * @param                       $completion_event_id
     *
     * @throws Exception
     */
    public function project_completion_event_saved(JobStruct $chunk, CompletionEventStruct $event, $completion_event_id): void
    {
        $model = new QualityReportModel($chunk);
        $model->resetScore($completion_event_id);
    }

    /**
     *
     * @param ChunkCompletionEventStruct $event
     *
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    public function alter_chunk_review_struct(ChunkCompletionEventStruct $event): void
    {
        $review = (new ChunkReviewDao())->findChunkReviews(new JobStruct(['id' => $event->id_job, 'password' => $event->password]))[0];

        $undo_data = $review->getUndoData();

        if (is_null($undo_data)) {
            throw new ValidationError('undo data is not available');
        }

        $this->_validateUndoData($event, $undo_data);

        $review->is_pass = $undo_data['is_pass'];
        $review->penalty_points = $undo_data['penalty_points'];
        $review->reviewed_words_count = $undo_data['reviewed_words_count'];
        $review->undo_data = null;

        ChunkReviewDao::updateStruct($review, [
            'fields' => [
                'is_pass',
                'penalty_points',
                'reviewed_words_count',
                'undo_data'
            ]
        ]);

        LoggerFactory::doJsonLog("CompletionEventController deleting event: " . var_export($event->getArrayCopy(), true));
    }

    /**
     * @param ChunkCompletionEventStruct $event
     * @param                                   $undo_data
     *
     * @throws ValidationError
     */
    protected function _validateUndoData(ChunkCompletionEventStruct $event, $undo_data): void
    {
        try {
            Utils::ensure_keys($undo_data, [
                'reset_by_event_id',
                'penalty_points',
                'reviewed_words_count',
                'is_pass'
            ]);
        } catch (Exception $e) {
            throw new ValidationError('undo data is missing some keys. ' . $e->getMessage());
        }

        if ($undo_data['reset_by_event_id'] != (string)$event->id) {
            throw new ValidationError('event does not match with latest revision data');
        }
    }

    /**
     * @param int $job_id
     * @param string $old_password
     * @param string $new_password
     * @param int $revision_number
     */
    public function review_password_changed(int $job_id, string $old_password, string $new_password, int $revision_number): void
    {
        $feedbackDao = new FeedbackDAO();
        $feedbackDao->updateFeedbackPassword($job_id, $old_password, $new_password, $revision_number);
    }

    /**
     * @param JobStruct $job
     * @param string $old_password
     */
    public function job_password_changed(JobStruct $job, string $old_password): void
    {
        $dao = new ChunkReviewDao();
        $dao->updatePassword($job->id, $old_password, $job->password);
    }

    /**
     *  Sets the QA model fom the uploaded file which was previously validated
     *  and added to the project structure.
     *
     * @param RecursiveArrayObject $projectStructure
     *
     * @return void
     * @throws ReflectionException
     */
    private function setQaModelFromJsonFile(RecursiveArrayObject $projectStructure): void
    {
        /** @var RecursiveArrayObject $model_json */
        $model_json = $projectStructure['features']['quality_framework'];

        $model_record = ModelDao::createModelFromJsonDefinition($model_json->toArray());

        $project = ProjectDao::findById(
            $projectStructure['id_project']
        );

        $dao = new ProjectDao(Database::obtain());
        $dao->updateField($project, 'id_qa_model', $model_record->id);
    }

    /**
     * Validate the project is valid in the scope of the ReviewExtended feature.
     * A project is valid if we can find a qa_model.json file inside a `__meta` folder.
     * The qa_model.json file must also be valid.
     *
     * If validation fails, add errors to the projectStructure.
     *
     * @param ArrayObject $projectStructure
     * @param string|null $jsonPath
     *
     */
    public static function loadAndValidateQualityFramework(ArrayObject &$projectStructure, ?string $jsonPath = null): void
    {
        if (get_called_class() instanceof ReviewExtended || get_called_class() == ReviewExtended::class) {
            return;
        }

        // Use Null Coalescing Operator to simplify checks for template or model
        $decoded_model = $projectStructure['qa_model_template'] ?? $projectStructure['qa_model'];

        // Still empty?
        if (empty($decoded_model)) {
            $decoded_model = self::loadModelFromPathOrDefault($projectStructure, $jsonPath);
        }

        // If decoding the model failed, register the error
        if (empty($decoded_model)) {
            $projectStructure['result']['errors'][] = [
                'code' => '-900',
                'message' => 'QA model failed to decode'
            ];
        }

        // Initialize features if not already set
        if (!isset($projectStructure['features'])) {
            $projectStructure['features'] = [];
        }

        // Append the QA model to the project structure
        $projectStructure['features']['quality_framework'] = $decoded_model;
    }

    /**
     * Get a model from path or default
     *
     * @param ArrayObject $projectStructure
     * @param string|null $jsonPath
     *
     * @return RecursiveArrayObject
     */
    private static function loadModelFromPathOrDefault(ArrayObject $projectStructure, ?string $jsonPath): RecursiveArrayObject
    {
        if (empty($qa_model)) {
            // Use null coalescing to simplify fallback logic
            $path = $jsonPath ?? AppConfig::$ROOT . '/inc/qa_model.json';
            $qa_model = file_get_contents($path);
        }

        $decoded_model = new RecursiveArrayObject(json_decode($qa_model, true));
        // Set the user ID to allow ownership in the QA models table
        $decoded_model['model']['uid'] = $projectStructure['uid'];

        return $decoded_model;
    }

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return ChunkReviewModel
     */
    public function getChunkReviewModel(ChunkReviewStruct $chunkReviewStruct): ChunkReviewModel
    {
        return new ChunkReviewModel($chunkReviewStruct);
    }

}
