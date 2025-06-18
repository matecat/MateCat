<?php

namespace Features;

use API\App\CreateProjectController;
use API\Commons\Exceptions\ValidationError;
use API\V1\NewController;
use ArrayObject;
use BasicFeatureStruct;
use Chunks_ChunkCompletionEventStruct;
use Constants;
use Database;
use Exception;
use Exceptions\NotFoundException;
use Features;
use Features\ProjectCompletion\CompletionEventStruct;
use Features\ReviewExtended\ChunkReviewModel;
use Features\ReviewExtended\Controller\API\Json\ProjectUrls;
use Features\ReviewExtended\IChunkReviewModel;
use Features\ReviewExtended\Model\QualityReportModel;
use Features\ReviewExtended\ReviewedWordCountModel;
use Features\ReviewExtended\ReviewUtils;
use Features\ReviewExtended\TranslationIssueModel;
use Features\TranslationEvents\Model\TranslationEvent;
use Features\TranslationEvents\Model\TranslationEventDao;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use INIT;
use Jobs_JobDao;
use Jobs_JobStruct;
use Klein\Klein;
use Log;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use LQA\ModelDao;
use Predis\Connection\ConnectionException;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use RecursiveArrayObject;
use ReflectionException;
use Revise\FeedbackDAO;
use Utils;
use WordCount\CounterModel;
use ZipArchive;

;

abstract class AbstractRevisionFeature extends BaseFeature {

    protected static $dependencies = [
            Features::TRANSLATION_VERSIONS
    ];

    public function __construct( BasicFeatureStruct $feature ) {
        parent::__construct( $feature );
    }

    /**
     * @param array $projectFeatures
     * @param       $controller NewController|CreateProjectController
     *
     * @return array
     * @throws Exception
     */
    public function filterCreateProjectFeatures( array $projectFeatures, $controller ): array {
        $projectFeatures[ static::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => static::FEATURE_CODE ] );

        return $projectFeatures;
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST', [ 'Features\ReviewExtended\Controller\ReviewsController', 'createReview' ] );
    }

    public static function projectUrls( $formatted ) {
        return new ProjectUrls( $formatted->getData() );
    }

    public function filterGetSegmentsResult( $data, Jobs_JobStruct $chunk ) {

        if ( empty( $data[ 'files' ] ) ) {
            // this means that there are no more segments after
            return $data;
        }

        reset( $data[ 'files' ] );

        $firstFile = current( $data[ 'files' ] );
        $lastFile  = end( $data[ 'files' ] );
        $firstSid  = $firstFile[ 'segments' ][ 0 ][ 'sid' ];

        if ( isset( $lastFile[ 'segments' ] ) and is_array( $lastFile[ 'segments' ] ) ) {
            $lastSegment = end( $lastFile[ 'segments' ] );
            $lastSid     = $lastSegment[ 'sid' ];

            $segment_translation_events = ( new TranslationEventDao() )->getLatestEventsInSegmentInterval(
                    $chunk->id, $firstSid, $lastSid );

            $by_id_segment = [];
            foreach ( $segment_translation_events as $record ) {
                $by_id_segment[ $record->id_segment ] = $record;
            }

            foreach ( $data[ 'files' ] as $file => $content ) {
                foreach ( $content[ 'segments' ] as $key => $segment ) {

                    if ( isset( $by_id_segment[ $segment[ 'sid' ] ] ) ) {
                        $data [ 'files' ] [ $file ] [ 'segments' ] [ $key ] [ 'revision_number' ] = ReviewUtils::sourcePageToRevisionNumber(
                                $by_id_segment[ $segment[ 'sid' ] ]->source_page
                        );
                    }

                }
            }
        }

        return $data;
    }

    /**
     * @param $password
     * @param $id_job
     *
     * @return mixed
     * @throws NotFoundException
     */
    public function filter_job_password_to_review_password( $password, $id_job ) {

        $chunk_review = ( new ChunkReviewDao() )->findChunkReviews( new Jobs_JobStruct( [ 'id' => $id_job, 'password' => $password ] ) )[ 0 ];

        if ( !$chunk_review ) {
            $chunk_review = ChunkReviewDao::findByReviewPasswordAndJobId( $password, $id_job );
        }

        if ( !$chunk_review ) {
            throw new NotFoundException( 'Review record was not found' );
        }

        return $chunk_review->review_password;
    }

    /**
     * Performs post project creation tasks for the current project.
     * Evaluates if a qa model is present in the feature options.
     * If so, then try to assign the defined qa_model.
     * If not, then try to find the qa_model from the project structure.
     *
     * @param $projectStructure
     *
     * @throws Exception
     */
    public function postProjectCreate( $projectStructure ) {

        if ( $this instanceof ReviewExtended ) {
            return;
        }

        $this->setQaModelFromJsonFile( $projectStructure );
        $this->createChunkReviewRecords( $projectStructure );
    }

    /**
     * @param Jobs_JobStruct[]|ChunkReviewStruct[] $chunksArray
     * @param Projects_ProjectStruct               $project
     * @param array                                $options
     *
     * @return array
     * @throws Exception
     */
    public function createQaChunkReviewRecords( array $chunksArray, Projects_ProjectStruct $project, $options = [] ) {

        $createdRecords = [];

        // expect one chunk
        if ( !isset( $options[ 'source_page' ] ) ) {
            $options[ 'source_page' ] = Constants::SOURCE_PAGE_REVISION;
        }

        foreach ( $chunksArray as $k => $chunk ) {
            $data = [
                    'id_project'  => $project->id,
                    'id_job'      => $chunk->id,
                    'password'    => $chunk->password,
                    'source_page' => $options[ 'source_page' ]
            ];

            if ( $k == 0 && array_key_exists( 'first_record_password', $options ) != null ) {
                $data[ 'review_password' ] = $options[ 'first_record_password' ];
            }

            $chunkReview      = ChunkReviewDao::createRecord( $data );
            $createdRecords[] = $chunkReview;

        }

        return $createdRecords;
    }

    /**
     * @param $projectStructure
     *
     * @throws Exception
     */
    protected function createChunkReviewRecords( $projectStructure ) {
        $project = Projects_ProjectDao::findById( $projectStructure[ 'id_project' ], 86400 );
        foreach ( $projectStructure[ 'array_jobs' ][ 'job_list' ] as $id_job ) {

            $chunkStruct = Jobs_JobDao::getById( $id_job, 0 );

            $iMax = 3;

            if ( isset( $projectStructure[ 'create_2_pass_review' ] ) && (bool)$projectStructure[ 'create_2_pass_review' ] ) {
                $iMax = 4;
            }

            for ( $i = 2; $i < $iMax; $i++ ) {
                $this->createQaChunkReviewRecords( $chunkStruct, $project, [ 'source_page' => $i ] );
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
    public function postJobSplitted( ArrayObject $projectStructure ) {

        /**
         * By definition, when running postJobSplitted callback the job is not splitted.
         * So we expect to find just one record in chunk_reviews for the job.
         * If we find more than one record, it's one record for each revision.
         *
         */

        $id_job                  = $projectStructure[ 'job_to_split' ];
        $previousRevisionRecords = ChunkReviewDao::findByIdJob( $id_job );
        $project                 = Projects_ProjectDao::findById( $projectStructure[ 'id_project' ], 86400 );

        ChunkReviewDao::deleteByJobId( $id_job );

        $chunksStructArray = Jobs_JobDao::getById( $id_job, 0 );

        $reviews = [];
        foreach ( $previousRevisionRecords as $review ) {

            // check if $review belongs to a deleted job
            $chunk = Jobs_JobDao::getByIdAndPassword( $review->id_job, $review->password );

            if ( !$chunk->isDeleted() ) {
                $reviews = array_merge( $reviews, $this->createQaChunkReviewRecords( $chunksStructArray, $project,
                        [
                                'first_record_password' => $review->review_password,
                                'source_page'           => $review->source_page
                        ]
                )
                );
            }
        }

        foreach ( $reviews as $review ) {
            $model = new ChunkReviewModel( $review );
            $model->recountAndUpdatePassFailResult( $project );
        }

    }

    /**
     * postJobMerged
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param $projectStructure
     *
     * @throws Exception
     */
    public function postJobMerged( $projectStructure ) {

        $id_job      = $projectStructure[ 'job_to_merge' ];
        $old_reviews = ChunkReviewDao::findByIdJob( $id_job );
        $project     = Projects_ProjectDao::findById( $projectStructure[ 'id_project' ], 86400 );

        $reviewGroupedData = [];

        foreach ( $old_reviews as $review ) {
            if ( !isset( $reviewGroupedData[ $review->source_page ] ) ) {
                $reviewGroupedData[ $review->source_page ] = [
                        'first_record_password' => $review->review_password
                ];
            }
        }

        ChunkReviewDao::deleteByJobId( $id_job );

        $chunksStructArray = Jobs_JobDao::getById( $id_job, 0 );

        $reviews = [];
        foreach ( $reviewGroupedData as $source_page => $data ) {
            $reviews = array_merge( $reviews, $this->createQaChunkReviewRecords(
                    $chunksStructArray,
                    $project,
                    [
                            'first_record_password' => $data[ 'first_record_password' ],
                            'source_page'           => $source_page
                    ]
            )
            );
        }

        foreach ( $reviews as $review ) {
            $model = new ChunkReviewModel( $review );
            $model->recountAndUpdatePassFailResult( $project );
        }
    }

    /**
     *
     * project_completion_event_saved
     *
     * @param Jobs_JobStruct        $chunk
     * @param CompletionEventStruct $event
     * @param                       $completion_event_id
     *
     * @throws Exception
     */
    public function project_completion_event_saved( Jobs_JobStruct $chunk, CompletionEventStruct $event, $completion_event_id ) {
        $model = new QualityReportModel( $chunk );
        $model->resetScore( $completion_event_id );
    }

    /**
     *
     * @param Chunks_ChunkCompletionEventStruct $event
     *
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function alter_chunk_review_struct( Chunks_ChunkCompletionEventStruct $event ) {

        $review = ( new ChunkReviewDao() )->findChunkReviews( new Jobs_JobStruct( [ 'id' => $event->id_job, 'password' => $event->password ] ) )[ 0 ];

        $undo_data = $review->getUndoData();

        if ( is_null( $undo_data ) ) {
            throw new ValidationError( 'undo data is not available' );
        }

        $this->_validateUndoData( $event, $undo_data );

        $review->is_pass              = $undo_data[ 'is_pass' ];
        $review->penalty_points       = $undo_data[ 'penalty_points' ];
        $review->reviewed_words_count = $undo_data[ 'reviewed_words_count' ];
        $review->undo_data            = null;

        ChunkReviewDao::updateStruct( $review, [
                'fields' => [
                        'is_pass',
                        'penalty_points',
                        'reviewed_words_count',
                        'undo_data'
                ]
        ] );

        Log::doJsonLog( "CompletionEventController deleting event: " . var_export( $event->getArrayCopy(), true ) );

    }

    /**
     * @param Chunks_ChunkCompletionEventStruct $event
     * @param                                   $undo_data
     *
     * @throws ValidationError
     */
    protected function _validateUndoData( Chunks_ChunkCompletionEventStruct $event, $undo_data ) {

        try {
            Utils::ensure_keys( $undo_data, [
                    'reset_by_event_id', 'penalty_points', 'reviewed_words_count', 'is_pass'
            ] );

        } catch ( Exception $e ) {
            throw new ValidationError( 'undo data is missing some keys. ' . $e->getMessage() );
        }

        if ( $undo_data[ 'reset_by_event_id' ] != (string)$event->id ) {
            throw new ValidationError( 'event does not match with latest revision data' );
        }

    }

    /**
     * @param $job_id
     * @param $old_password
     * @param $new_password
     * @param $revision_number
     */
    public function review_password_changed( $job_id, $old_password, $new_password, $revision_number ) {
        $feedbackDao = new FeedbackDAO();
        $feedbackDao->updateFeedbackPassword( $job_id, $old_password, $new_password, $revision_number );
    }

    /**
     * @param Jobs_JobStruct $job
     * @param                $old_password
     */
    public function job_password_changed( Jobs_JobStruct $job, $old_password ) {
        $dao = new ChunkReviewDao();
        $dao->updatePassword( $job->id, $old_password, $job->password );
    }

    /**
     *  Sets the QA model fom the uploaded file which was previously validated
     *  and added to the project structure.
     *
     * @param $projectStructure
     *
     * @return void
     * @throws ReflectionException
     */
    private function setQaModelFromJsonFile( $projectStructure ) {

        /** @var RecursiveArrayObject $model_json */
        $model_json = $projectStructure[ 'features' ][ 'quality_framework' ];

        $model_record = ModelDao::createModelFromJsonDefinition( $model_json->toArray() );

        $project = Projects_ProjectDao::findById(
                $projectStructure[ 'id_project' ]
        );

        $dao = new Projects_ProjectDao( Database::obtain() );
        $dao->updateField( $project, 'id_qa_model', $model_record->id );
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
     * @throws ConnectionException
     * @throws ReflectionException
     * @throws Exception
     */
    public static function loadAndValidateQualityFramework( ArrayObject &$projectStructure, ?string $jsonPath = null ) {
        
        if ( get_called_class() instanceof ReviewExtended || get_called_class() == ReviewExtended::class ) {
            return;
        }

        // Use Null Coalescing Operator to simplify checks for template or model
        $decoded_model = $projectStructure[ 'qa_model_template' ] ?? $projectStructure[ 'qa_model' ];

        // Try to load from ZIP file if no model is injected
        if ( empty( $decoded_model ) ) {
            $decoded_model = self::extractQaModelFromZip( $projectStructure[ 'uploadToken' ] );
        }

        // Still empty?
        if ( empty( $decoded_model ) ) {
            $decoded_model = self::loadModelFromPathOrDefault( $projectStructure, $jsonPath );
        }

        // If decoding the model failed, register the error
        if ( empty( $decoded_model ) ) {
            $projectStructure[ 'result' ][ 'errors' ][] = [
                    'code'    => '-900',
                    'message' => 'QA model failed to decode'
            ];
        }

        // Initialize features if not already set
        if ( !isset( $projectStructure[ 'features' ] ) ) {
            $projectStructure[ 'features' ] = [];
        }

        // Append the QA model to the project structure
        $projectStructure[ 'features' ][ 'quality_framework' ] = $decoded_model;

    }

    /**
     * Get a model from path or default
     *
     * @param ArrayObject $projectStructure
     * @param string|null $jsonPath
     *
     * @return array|RecursiveArrayObject
     */
    private static function loadModelFromPathOrDefault( ArrayObject $projectStructure, ?string $jsonPath ) {

        if ( empty( $qa_model ) ) {
            // Use null coalescing to simplify fallback logic
            $path     = $jsonPath ?? INIT::$ROOT . '/inc/qa_model.json';
            $qa_model = file_get_contents( $path );
        }

        $decoded_model = new RecursiveArrayObject( json_decode( $qa_model, true ) );
        // Set the user ID to allow ownership in the QA models table
        $decoded_model[ 'model' ][ 'uid' ] = $projectStructure[ 'uid' ];

        return $decoded_model;
    }

    /**
     * Extract QA model from ZIP file
     *
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     */
    private static function extractQaModelFromZip( $uploadToken ) {
        $fs       = FilesStorageFactory::create();
        $zip_file = $fs->getTemporaryUploadedZipFile( $uploadToken );

        if ( $zip_file === false ) {
            return null;
        }

        $zip      = new ZipArchive();
        $qa_model = null;
        if ( $zip->open( $zip_file ) === true ) {
            $qa_model = $zip->getFromName( '__meta/qa_model.json' );
            $zip->close();
        }

        if ( AbstractFilesStorage::isOnS3() ) {
            unlink( $zip_file );
        }

        return $qa_model;
    }

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return IChunkReviewModel
     */
    public function getChunkReviewModel( ChunkReviewStruct $chunkReviewStruct ) {
        return new ChunkReviewModel( $chunkReviewStruct );
    }

    /**
     * @param TranslationEvent    $translation
     * @param CounterModel        $jobWordCounter
     * @param ChunkReviewStruct[] $chunkReviews
     *
     * @return ReviewedWordCountModel
     */
    public function getReviewedWordCountModel( TranslationEvent $translation, CounterModel $jobWordCounter, array $chunkReviews = [] ) {
        return new ReviewedWordCountModel( $translation, $jobWordCounter, $chunkReviews );
    }

    /**
     * @param $id_job
     * @param $password
     * @param $issue
     *
     * @return mixed
     */
    public function getTranslationIssueModel( $id_job, $password, $issue ) {
        return new TranslationIssueModel( $id_job, $password, $issue );
    }

}
