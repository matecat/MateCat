<?php

namespace Features ;

use Features\ReviewImproved\ChunkReviewModel;
use INIT;
use Log ;
use FilesStorage ;
use LQA\ChunkReviewDao;
use Translations_SegmentTranslationStruct;
use ZipArchive ;
use Chunks_ChunkDao  ;
use SegmentTranslationModel;
use Features\ReviewImproved\Observer\SegmentTranslationObserver ;
use Features\ReviewImproved\Controller;
use Projects_MetadataDao;
use ChunkOptionsModel;

class ReviewImproved extends BaseFeature {

    private $feature_options ;

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
        $chunk_review = \LQA\ChunkReviewDao::findByReviewPasswordAndJobId(
            $review_password, $id_job );

        if ( ! $chunk_review ) {
            throw new \Exceptions_RecordNotFound('Review record was not found');
        }

        return $chunk_review->password ;
    }

    public function filter_job_password_to_review_password( $password, $id_job ) {
        $chunk_reviews = \LQA\ChunkReviewDao::findChunkReviewsByChunkIds(
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
     * This filter is to store the review_password in the data strucutre
     * to be passed back to the javascript.
     */
    public function filter_manage_projects_loaded( $projects ) {
        $chunks = array();
        foreach( $projects as $project ) {
            foreach( $project['jobs'] as $job ) {
                foreach( array_values($job) as $chunk ) {
                    $chunks[] = array( $chunk['id'], $chunk['password']);
                }
            }
        }

        $chunk_reviews = \LQA\ChunkReviewDao::findChunkReviewsByChunkIds( $chunks );

        foreach( $projects as $k => $project ) {
            foreach( $project['jobs'] as $kk => $job ) {
                foreach( $job as $kkk => $chunk ) {

                    /**
                     * Inner cycle to match chunk_reviews records and modify
                     * the data structure.
                     */
                    foreach( $chunk_reviews as $chunk_review ) {
                        if ( $chunk_review->id_job == $chunk['id'] &&
                            $chunk_review->password == $chunk['password']
                        ) {
                            $projects[$k]['jobs'][$kk][$kkk]['review_password'] = $chunk_review->review_password ;
                        }
                    }
                }
            }
        }

        return $projects ;
    }

    /**
     * Performs post project creation tasks for the current project.
     * Evaluates if a qa model is present in the feature options.
     * If so, then try to assign the defined qa_model.
     * If not, then try to find the qa_model from the project structure.
     */
    public function postProjectCreate($projectStructure) {
        \Log::doLog( $this->feature );

        $this->feature_options = json_decode( $this->feature->options );

        if ( property_exists($this->feature_options, 'id_qa_model' ) ) {
            $this->setQaModelFromFeatureOptions($projectStructure);
        }
        else {
            $this->setQaModelFromJsonFile( $projectStructure );
        }

        $id_job = $projectStructure['array_jobs']['job_list'][0];
        $this->createQaChunkReviewRecord( $id_job, $projectStructure );
    }

    /**
     * postJobSplitted
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     */
    public function postJobSplitted(\ArrayObject $projectStructure) {
        $id_job = $projectStructure['array_jobs']['job_list'][0] ;
        $old_reviews = ChunkReviewDao::findByIdJob( $id_job );
        $first_password = $old_reviews[0]->review_password ;

        ChunkReviewDao::deleteByJobId( $id_job );

        $this->createQaChunkReviewRecord( $id_job, $projectStructure, array(
            'first_record_password' => $first_password
        ));
        $id_project = $projectStructure['id_project'];

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

        $this->createQaChunkReviewRecord( $id_job, $projectStructure, array(
            'first_record_password' => $first_password
        ));

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
        $this->feature_options = json_decode( $this->feature->options );

        if ( $this->feature_options->id_qa_model ) {
            // pass
        } else {
            $this->validateModeFromJsonFile($projectStructure);
        }
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
        $translation_model->attach( new SegmentTranslationObserver());
        $translation_model->notify();
    }

    /**
     * @param $array_jobs The jobs array coming from the project_structure
     *
     */
    private function createQaChunkReviewRecord( $id_job, $projectStructure, $options=array() ) {
        $id_project = $projectStructure['id_project'];
        $chunks = Chunks_ChunkDao::getByJobIdProjectAndIdJob( $id_project, $id_job ) ;

        foreach( $chunks as $k => $chunk ) {
            $data = array(
                'id_project' => $id_project,
                'id_job'     => $chunk->id,
                'password'   => $chunk->password
            );

            if ( $k == 0 && array_key_exists('first_record_password', $options) != null ) {
                $data['review_password'] = $options['first_record_password'];
            }

            \LQA\ChunkReviewDao::createRecord( $data );
        }
    }

    /**
     * Sets the QA model fom the uploaded file which was previously validated
     * and added to the project structure.
     */
    private function setQaModelFromJsonFile( $projectStructure ) {

        $model_json = $projectStructure['features']
            ['review_improved']['__meta']['qa_model'];

        $model_record = \LQA\ModelDao::createModelFromJsonDefinition( $model_json );

        $project = \Projects_ProjectDao::findById(
            $projectStructure['id_project']
        );

        $dao = new \Projects_ProjectDao( \Database::obtain() );
        $dao->updateField( $project, 'id_qa_model', $model_record->id );
    }

    /**
     * This method  is used to  assign the  qa_model to the project based on the
     * option specified in the feature record itself.
     *
     * This was originally developed to avoid the need to pass the qa_model.json
     * inside the zip file each time. This could be used to perform a conditonal
     * check on the need for the qa_model.json file to be passed at each project
     * creation.
     */
    private function setQaModelFromFeatureOptions($projectStructure) {
        $project = \Projects_ProjectDao::findById( $projectStructure['id_project'] );

        $dao = new \Projects_ProjectDao( \Database::obtain() );
        $dao->updateField( $project, 'id_qa_model', $this->feature_options->id_qa_model );

    }

    /**
     * Validate the project is valid in the scope of ReviewImproved feature.
     * A project is valid if we area able to find a qa_model.json file inside
     * a __meta folder. The qa_model.json file must be valid too.
     *
     * If validation fails, adds errors to the projectStructure.
     */

    private function validateModeFromJsonFile( $projectStructure ) {
        // detect if the project created was a zip file, in which case try to detect 
        // id_qa_model from json file. 
        // otherwise assign the default model 
        
        $qa_model = FALSE; 
        $fs = new FilesStorage();
        $zip_file = $fs->getTemporaryUploadedZipFile( $projectStructure['uploadToken'] );

        \Log::doLog( $zip_file ); 

        if ( $zip_file !== FALSE ) {
            $zip = new ZipArchive();
            $zip->open( $zip_file );
            $qa_model = $zip->getFromName( '__meta/qa_model.json');
        }

        // File is not a zip OR model was not found in zip

        \Log::doLog( $qa_model ); 

        if ( $qa_model === FALSE ) {
            $qa_model = file_get_contents( INIT::$ROOT . '/inc/qa_model.json');
        }

        $decoded_model = json_decode( $qa_model, TRUE ) ;

        if ( $decoded_model === null ) {
            $projectStructure['result']['errors'][ ] = array(
                'code' => '-900',  // TODO: decide how to assign such errors
                'message' => 'QA model failed to decode'
            );
        }

        // TODO: implement other validations


        /**
         * Append the qa model to the project structure for later use.
         */
        if ( ! array_key_exists('features', $projectStructure ) ) {
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
        $klein->respond('GET', '/quality_report/[:id_job]/[:password]', function ($request, $response, $service, $app) {
            $controller = new Controller\QualityReportController( $request, $response, $service, $app);
            $template_path = dirname(__FILE__) . '/ReviewImproved/View/Html/quality_report.html' ;
            $controller->setView( $template_path );
            $controller->respond();
        });
    }

}
