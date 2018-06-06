<?php

namespace Features ;

use API\V2\Exceptions\ValidationError;
use Chunks_ChunkCompletionEventStruct;
use Chunks_ChunkDao;
use Contribution\ContributionStruct;
use Database;
use Exception;
use Features\ReviewImproved\ChunkReviewModel;
use INIT;
use FilesStorage ;
use Log;
use LQA\ChunkReviewDao;
use LQA\ModelDao;
use Projects_ProjectDao;
use Translations_SegmentTranslationStruct;
use Utils;
use ZipArchive ;
use SegmentTranslationModel;
use Features\ReviewImproved\Observer\SegmentTranslationObserver ;
use Features\ReviewImproved\Controller;
use Projects_ProjectStruct ;
use Jobs_JobStruct ;

use Features\ProjectCompletion\CompletionEventStruct ;

use Chunks_ChunkStruct ;
use Features\ReviewImproved\Model\ArchivedQualityReportModel ;
use Features\ReviewImproved\Model\QualityReportModel ;

abstract class AbstractRevisionFeature extends BaseFeature {

    /**
     * In ReviewImproved, UI forces the `propagation` parameter to false to avoid prompt and autopropagation of
     * revision status changes.
     *
     * This param must be reset to default value `true` when contribution is evaluted, otherwise the
     * TM won't receive UPDATE when a segment is updated.
     *
     * XXX: not sure this was the best way to solve this problem.
     *
     * @param ContributionStruct     $contributionStruct
     * @param Projects_ProjectStruct $project
     *
     * @return ContributionStruct
     */
    public function filterContributionStructOnSetTranslation( ContributionStruct $contributionStruct, Projects_ProjectStruct $project ) {

        if ( $contributionStruct->fromRevision ) {
            $contributionStruct->propagationRequest = true ;
        }

        return $contributionStruct ;
    }

    /**
     * filter_review_password
     *
     * If this method is reached it means that the project we are
     * working on has ReviewImproved feature enabled, and that we
     * are in reivew mode.
     *
     * Assuming the provided password is a "review_password".
     * This review password is checked against the `qa_chunk_reviews`.
     * If not found, raise an exception.
     * If found, override the input password with job password.
     *
     */
    public function filter_review_password_to_job_password( $review_password, $id_job ) {
        $chunk_review = ChunkReviewDao::findByReviewPasswordAndJobId(
                $review_password, $id_job );

        if ( ! $chunk_review ) {
            throw new \Exceptions_RecordNotFound('Review record was not found');
        }

        return $chunk_review->password ;
    }

    public function filter_job_password_to_review_password( $password, $id_job ) {
        $chunk_reviews = ChunkReviewDao::findChunkReviewsByChunkIds(
                array( array($id_job, $password ) )
        );

        $chunk_review = $chunk_reviews[0];

        if ( ! $chunk_review ) {
            throw new \Exceptions_RecordNotFound('Review record was not found');
        }

        return $chunk_review->review_password ;
    }


    public function filter_get_segments_segment_data( $seg ) {
        if( isset($seg['edit_distance']) ) {
            $seg['edit_distance'] = round( $seg['edit_distance'] / 1000, 2 );
        } else {
            $seg['edit_distance'] = 0;
        }
        return $seg;
    }

    public function filter_get_segments_optional_fields( $options ) {
        $options['optional_fields'][] = 'edit_distance';
        return $options ;
    }


    /**
     *
     * @param $project
     */
    public function filter_manage_single_project( $project ) {
        $chunks = array();

        foreach( $project['jobs'] as $job ) {
            $chunks[] = array( $job['id'], $job['password'] );
        }

        $chunk_reviews = ChunkReviewDao::findChunkReviewsByChunkIds( $chunks );

        foreach( $project['jobs'] as $kk => $job ) {
            /**
             * Inner cycle to match chunk_reviews records and modify
             * the data structure.
             */
            foreach( $chunk_reviews as $chunk_review ) {
                if ( $chunk_review->id_job == $job['id'] &&
                        $chunk_review->password == $job['password']
                ) {
                    $project['jobs'][$kk]['review_password'] = $chunk_review->review_password ;
                }
            }
        }

        return $project ;
    }

    /**
     * Performs post project creation tasks for the current project.
     * Evaluates if a qa model is present in the feature options.
     * If so, then try to assign the defined qa_model.
     * If not, then try to find the qa_model from the project structure.
     * @throws \Exceptions\ValidationError
     */
    public function postProjectCreate($projectStructure) {
        Log::doLog( $this->feature );

        $this->setQaModelFromJsonFile( $projectStructure );

        $this->createChunkReviewRecords( $projectStructure );

    }

    /**
     * @param       $id_job
     * @param       $id_project
     * @param array $options
     *
     * @throws \Exceptions\ValidationError
     */
    protected function createQaChunkReviewRecord( $id_job, $id_project, $options = [] ) {

        $chunks     = Chunks_ChunkDao::getByIdProjectAndIdJob( $id_project, $id_job );

        foreach ( $chunks as $k => $chunk ) {
            $data = [
                    'id_project' => $id_project,
                    'id_job'     => $chunk->id,
                    'password'   => $chunk->password
            ];

            if ( $k == 0 && array_key_exists( 'first_record_password', $options ) != null ) {
                $data[ 'review_password' ] = $options[ 'first_record_password' ];
            }

            ChunkReviewDao::createRecord( $data );
        }

    }

    /**
     * @param $projectStructure
     *
     * @throws \Exceptions\ValidationError
     */
    protected function createChunkReviewRecords( $projectStructure ) {
        foreach ( $projectStructure[ 'array_jobs' ][ 'job_list' ] as $id_job ) {
            $this->createQaChunkReviewRecord( $id_job, $projectStructure[ 'id_project' ] );
        }
    }

    /**
     * postJobSplitted
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param \ArrayObject $projectStructure
     *
     * @throws \Exceptions\ValidationError
     */
    public function postJobSplitted( \ArrayObject $projectStructure ) {

        $id_job = $projectStructure['job_to_split'];
        $old_reviews = ChunkReviewDao::findByIdJob( $id_job );
        $first_password = $old_reviews[0]->review_password ;

        ChunkReviewDao::deleteByJobId( $id_job );

        $this->createQaChunkReviewRecord( $id_job, $projectStructure[ 'id_project' ], [
                'first_record_password' => $first_password
        ] );

        $reviews = ChunkReviewDao::findByIdJob( $id_job );
        foreach( $reviews as $review ) {
            $model = new ChunkReviewModel($review);
            $model->recountAndUpdatePassFailResult();
        }

    }

    /**
     * postJobMerged
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     *
     * @param $projectStructure
     *
     * @throws \Exceptions\ValidationError
     */
    public function postJobMerged( $projectStructure ) {
        $id_job               = $projectStructure[ 'job_to_merge' ];
        $old_reviews          = ChunkReviewDao::findByIdJob( $id_job );
        $first_password       = $old_reviews[ 0 ]->review_password;
        $penalty_points       = 0;
        $reviewed_words_count = 0;

        ChunkReviewDao::deleteByJobId( $id_job );

        foreach($old_reviews as $row ) {
            $penalty_points = $penalty_points + $row->penalty_points;
            $reviewed_words_count = $reviewed_words_count + $row->reviewed_words_count ;
        }

        $this->createQaChunkReviewRecord( $id_job, $projectStructure[ 'id_project' ], [
                'first_record_password' => $first_password
        ] );

        $new_reviews = ChunkReviewDao::findByIdJob( $id_job );
        $new_reviews[0]->penalty_points = $penalty_points;
        $new_reviews[0]->reviewed_words_count = $reviewed_words_count ;

        $model = new ChunkReviewModel( $new_reviews[0] );
        $model->updatePassFailResult();

    }

    /**
     * Entry point for project data validation for this feature.
     */
    public function validateProjectCreation($projectStructure)  {
        self::loadAndValidateModelFromJsonFile($projectStructure);
    }

    /**
     * @param $new_translation
     * @param $old_translation
     */
    public function setTranslationCommitted($params) {
        $new_translation = $params['translation'];
        $old_translation = $params['old_translation'];

        $new_translation_struct =  new Translations_SegmentTranslationStruct( $new_translation );
        $old_translation_struct = new Translations_SegmentTranslationStruct( $old_translation );

        $translation_model = new SegmentTranslationModel( $new_translation_struct );
        $translation_model->setOldTranslation( $old_translation_struct );

        /**
         * This implementation may seem overkill since we are already into review improved feature
         * so we could avoid to delegate to an observer. This is done with aim to the future when
         * the SegmentTranslationModel will be used directly into setTranslation controller.
         */
        $translation_model->attach( new SegmentTranslationObserver() );
        $translation_model->notify();
    }

    /**
     * project_completion_event_saved
     *
     * @param Chunks_ChunkStruct $chunk
     * @param CompletionEventStruct $event
     * @param $completion_event_id
     */
    public function project_completion_event_saved( Chunks_ChunkStruct $chunk, CompletionEventStruct $event, $completion_event_id ) {
        if ( $event->is_review ) {
            $model = new ArchivedQualityReportModel( $chunk );
            $model->saveWithUID( $event->uid );
        }
        else {
            $model = new QualityReportModel( $chunk ) ;
            $model->resetScore( $completion_event_id );
        }
    }

    /**
     *
     * @param Chunks_ChunkCompletionEventStruct $event
     *
     * @throws ValidationError
     * @throws \Exceptions\ValidationError
     * @throws \ReflectionException
     */
    public function alter_chunk_review_struct( Chunks_ChunkCompletionEventStruct $event ){

        $review = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword(
                $event->id_job,
                $event->password
        );

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

        Log::doLog( "CompletionEventController deleting event: " . var_export( $event->getArrayCopy(), true ) );

    }

    /**
     * @param Chunks_ChunkCompletionEventStruct $event
     * @param                                   $undo_data
     *
     * @throws ValidationError
     */
    protected function _validateUndoData( Chunks_ChunkCompletionEventStruct $event , $undo_data ) {

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

    public function job_password_changed(Jobs_JobStruct $job, $new_password ) {
        $dao = new ChunkReviewDao();
        $dao->updatePassword( $job->id, $job->password, $new_password );
    }

    /**
     * Sets the QA model fom the uploaded file which was previously validated
     * and added to the project structure.
     */
    private function setQaModelFromJsonFile( $projectStructure ) {

        $model_json = $projectStructure['features'][ 'review_improved' ]['__meta']['qa_model'];

        $model_record = ModelDao::createModelFromJsonDefinition( $model_json );

        $project = Projects_ProjectDao::findById(
                $projectStructure['id_project']
        );

        $dao = new Projects_ProjectDao( Database::obtain() );
        $dao->updateField( $project, 'id_qa_model', $model_record->id );
    }

    /**
     * Validate the project is valid in the scope of ReviewImproved feature.
     * A project is valid if we area able to find a qa_model.json file inside
     * a __meta folder. The qa_model.json file must be valid too.
     *
     * If validation fails, adds errors to the projectStructure.
     *
     * @param      $projectStructure
     * @param null|string $jsonPath
     */

    public static function loadAndValidateModelFromJsonFile( $projectStructure, $jsonPath = null ) {
        // detect if the project created was a zip file, in which case try to detect
        // id_qa_model from json file.
        // otherwise assign the default model

        $qa_model = false;
        $fs = new FilesStorage();
        $zip_file = $fs->getTemporaryUploadedZipFile( $projectStructure['uploadToken'] );

        Log::doLog( $zip_file );

        if ( $zip_file !== false ) {
            $zip = new ZipArchive();
            $zip->open( $zip_file );
            $qa_model = $zip->getFromName( '__meta/qa_model.json');
        }

        // File is not a zip OR model was not found in zip

        Log::doLog( "QA model is : " . var_export( $qa_model, true ) ) ;

        if ( $qa_model === false ) {
            if( $jsonPath == null ){
                $qa_model = file_get_contents( INIT::$ROOT . '/inc/qa_model.json');
            } else {
                $qa_model = file_get_contents( $jsonPath );
            }
        }

        $decoded_model = json_decode( $qa_model, true ) ;

        if ( $decoded_model === null ) {
            $projectStructure['result']['errors'][ ] = array(
                    'code' => '-900',  // TODO: decide how to assign such errors
                    'message' => 'QA model failed to decode'
            );
        }

        /**
         * Append the qa model to the project structure for later use.
         */
        if ( ! array_key_exists( 'features', $projectStructure ) ) {
            $projectStructure['features'] = array();
        }

        $projectStructure['features'] = array(
                'review_improved' => array(
                        '__meta' => array(
                                'qa_model' => $decoded_model
                        )
                )
        );

    }


    /**
     * Install routes for this plugin
     *
     * @param \Klein\Klein $klein
     */
    public static function loadRoutes( \Klein\Klein $klein ) {
        $klein->respond('GET', '/quality_report/[:id_job]/[:password]',                    array(__CLASS__, 'callbackQualityReport')  );
        $klein->respond('GET', '/quality_report/[:id_job]/[:password]/versions/[:version]', array(__CLASS__, 'callbackQualityReport')  );
    }

    public static function callbackQualityReport($request, $response, $service, $app) {
        $controller = new Controller\QualityReportController( $request, $response, $service, $app);
        $template_path = dirname(__FILE__) . '/ReviewImproved/View/Html/quality_report.html' ;
        $controller->setView( $template_path );
        $controller->respond();
    }

}
