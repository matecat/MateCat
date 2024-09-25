<?php

namespace Features;

use API\Commons\Exceptions\ValidationError;
use BasicFeatureStruct;
use Chunks_ChunkCompletionEventStruct;
use Chunks_ChunkStruct;
use Constants;
use createProjectController;
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
use NewController;
use Predis\Connection\ConnectionException;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use ReflectionException;
use Revise\FeedbackDAO;
use RevisionFactory;
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
     * @param $controller NewController|createProjectController
     *
     * @return array
     * @throws Exception
     */
    public function filterCreateProjectFeatures( array $projectFeatures, $controller ): array {
        $projectFeatures[ static::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => static::FEATURE_CODE ] );
        return $projectFeatures;
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST',
                'Features\ReviewExtended\Controller\ReviewsController', 'createReview' );
    }

    public static function projectUrls( $formatted ) {
        return new ProjectUrls( $formatted->getData() );
    }

    public function filterGetSegmentsResult( $data, Chunks_ChunkStruct $chunk ) {

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
     * @param ChunkReviewStruct      $chunkReview
     * @param Projects_ProjectStruct $projectStruct
     *
     * @throws Exception
     */
    public function chunkReviewRecordCreated( ChunkReviewStruct $chunkReview, Projects_ProjectStruct $projectStruct ) {
        // This is needed to properly populate advancement wc for ICE matches
        ( new ChunkReviewModel( $chunkReview ) )->recountAndUpdatePassFailResult( $projectStruct );
    }

    /**
     * filter_review_password_to_job_password
     *
     * If this method is reached it means that the project we are
     * working on has ReviewExtended feature enabled, and that we
     * are in review mode.
     *
     * Assuming the provided password is a "review_password".
     * This review password is checked against the `qa_chunk_reviews`.
     * If not found, raise an exception.
     * If found, override the input password with job password.
     *
     * @param string $review_password
     * @param int    $id_job
     * @param int    $source_page
     *
     * @return ChunkReviewStruct
     * @throws NotFoundException
     */
    public function filter_review_password_to_job_password( ChunkReviewStruct $chunkReviewStruct, $source_page ) {
        $chunk_review = ( new ChunkReviewDao() )->findByJobIdReviewPasswordAndSourcePage( $chunkReviewStruct->id_job, $chunkReviewStruct->review_password, $source_page );

        if ( !$chunk_review ) {
            throw new NotFoundException( 'Review record was not found' );
        }

        return $chunk_review;
    }

    /**
     * @param $password
     * @param $id_job
     *
     * @return mixed
     * @throws NotFoundException
     */
    public function filter_job_password_to_review_password( $password, $id_job ) {

        $chunk_review = ( new ChunkReviewDao() )->findChunkReviews( new Chunks_ChunkStruct( [ 'id' => $id_job, 'password' => $password ] ) )[ 0 ];

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
        $this->setQaModelFromJsonFile( $projectStructure );
        $this->createChunkReviewRecords( $projectStructure );
    }

    /**
     * @param Chunks_ChunkStruct[]|ChunkReviewStruct[] $chunksArray
     * @param Projects_ProjectStruct                   $project
     * @param array                                    $options
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

            $chunkReview = ChunkReviewDao::createRecord( $data );
            $project->getFeaturesSet()->run( 'chunkReviewRecordCreated', $chunkReview, $project );

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

            /**
             * @var $chunkStruct Chunks_ChunkStruct[]
             */
            $chunkStruct = Jobs_JobDao::getById( $id_job, 0, new Chunks_ChunkStruct() );

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
     * @param \ArrayObject $projectStructure
     *
     * @throws Exception
     *
     */
    public function postJobSplitted( \ArrayObject $projectStructure ) {

        /**
         * By definition, when running postJobSplitted callback the job is not splitted.
         * So we expect to find just one record in chunk_reviews for the job.
         * If we find more than one record, it's one record for each revision.
         *
         */

        $id_job                  = $projectStructure[ 'job_to_split' ];
        $previousRevisionRecords = ChunkReviewDao::findByIdJob( $id_job );
        $project                 = Projects_ProjectDao::findById( $projectStructure[ 'id_project' ], 86400 );

        $revisionFactory = RevisionFactory::initFromProject( $project );

        ChunkReviewDao::deleteByJobId( $id_job );

        /**
         * @var $chunksStructArray Chunks_ChunkStruct[]
         */
        $chunksStructArray = Jobs_JobDao::getById( $id_job, 0, new Chunks_ChunkStruct() );


        $reviews = [];
        foreach ( $previousRevisionRecords as $review ) {

            // check if $review belongs to a deleted job
            $chunk = Jobs_JobDao::getByIdAndPassword( $review->id_job, $review->password );

            if ( !$chunk->wasDeleted() ) {
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
            $model = $revisionFactory->getChunkReviewModel( $review );
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

        $revisionFactory = RevisionFactory::initFromProject( $project );

        $reviewGroupedData = [];

        foreach ( $old_reviews as $review ) {
            if ( !isset( $reviewGroupedData[ $review->source_page ] ) ) {
                $reviewGroupedData[ $review->source_page ] = [
                        'first_record_password' => $review->review_password
                ];
            }
        }

        ChunkReviewDao::deleteByJobId( $id_job );

        /** @var $chunksStructArray Chunks_ChunkStruct[] */
        $chunksStructArray = Jobs_JobDao::getById( $id_job, 0, new Chunks_ChunkStruct() );

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
            $model = $revisionFactory->getChunkReviewModel( $review );
            $model->recountAndUpdatePassFailResult( $project );
        }
    }

    /**
     * Entry point for project data validation for this feature.
     *
     * @param $projectStructure
     *
     * @throws ConnectionException
     * @throws \Exceptions\ValidationError
     * @throws ReflectionException
     */
    public function validateProjectCreation( $projectStructure ) {
        self::loadAndValidateModelFromJsonFile( $projectStructure );
    }

    /**
     *
     * project_completion_event_saved
     *
     * @param Chunks_ChunkStruct    $chunk
     * @param CompletionEventStruct $event
     * @param                       $completion_event_id
     */
    public function project_completion_event_saved( Chunks_ChunkStruct $chunk, CompletionEventStruct $event, $completion_event_id ) {
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

        $review = ( new ChunkReviewDao() )->findChunkReviews( new Chunks_ChunkStruct( [ 'id' => $event->id_job, 'password' => $event->password ] ) )[ 0 ];

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
        $feedbackDao->updateFeedbackPassword($job_id, $old_password, $new_password, $revision_number);
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
     * @throws \Exceptions\ValidationError
     */
    private function setQaModelFromJsonFile( $projectStructure ) {

        $model_json = $projectStructure[ 'features' ][ 'review_extended' ][ '__meta' ][ 'qa_model' ];

        $model_record = ModelDao::createModelFromJsonDefinition( $model_json );

        $project = Projects_ProjectDao::findById(
                $projectStructure[ 'id_project' ]
        );

        $dao = new Projects_ProjectDao( Database::obtain() );
        $dao->updateField( $project, 'id_qa_model', $model_record->id );
    }

    /**
     * Validate the project is valid in the scope of ReviewExtended feature.
     * A project is valid if we area able to find a qa_model.json file inside
     * a __meta folder. The qa_model.json file must be valid too.
     *
     * If validation fails, adds errors to the projectStructure.
     *
     * @param             $projectStructure
     * @param null|string $jsonPath
     *
     * @throws ConnectionException
     * @throws ReflectionException
     * @throws \Exceptions\ValidationError
     */

    public static function loadAndValidateModelFromJsonFile( &$projectStructure, $jsonPath = null ) {

        // CASE 1 there is an injected QA template id
        if ( isset( $projectStructure[ 'qa_model_template' ] ) and null !== $projectStructure[ 'qa_model_template' ] ) {
            $decoded_model = $projectStructure[ 'qa_model_template' ];
        } // CASE 2 there a is an injected qa_model
        elseif ( isset( $projectStructure[ 'qa_model' ] ) and null !== $projectStructure[ 'qa_model' ] ) {
            $decoded_model = $projectStructure[ 'qa_model' ];
        } // CASE3 otherwise
        else {
            // detect if the project created was a zip file, in which case try to detect
            // id_qa_model from json file.
            // otherwise assign the default model

            $qa_model = false;
            $fs       = FilesStorageFactory::create();
            $zip_file = $fs->getTemporaryUploadedZipFile( $projectStructure[ 'uploadToken' ] );

            Log::doJsonLog( $zip_file );

            if ( $zip_file !== false ) {
                $zip = new ZipArchive();
                $zip->open( $zip_file );
                $qa_model = $zip->getFromName( '__meta/qa_model.json' );

                if ( AbstractFilesStorage::isOnS3() ) {
                    unlink( $zip_file );
                }

            }

            // File is not a zip OR model was not found in zip

            Log::doJsonLog( "QA model is : " . var_export( $qa_model, true ) );

            if ( $qa_model === false ) {
                if ( $jsonPath == null ) {
                    $qa_model = file_get_contents( INIT::$ROOT . '/inc/qa_model.json' );
                } else {
                    $qa_model = file_get_contents( $jsonPath );
                }
            }

            $decoded_model = json_decode( $qa_model, true );
        }

        if ( $decoded_model === null ) {
            $projectStructure[ 'result' ][ 'errors' ][] = [
                    'code'    => '-900',  // TODO: decide how to assign such errors
                    'message' => 'QA model failed to decode'
            ];
        }

        /**
         * Append the qa model to the project structure for later use.
         */
        if ( !isset( $projectStructure[ 'features' ] ) ) {
            $projectStructure[ 'features' ] = [];
        }

        $projectStructure[ 'features' ] = [
                'review_extended' => [
                        '__meta' => [
                                'qa_model' => $decoded_model
                        ]
                ]
        ];

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
