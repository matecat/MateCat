<?php

namespace Features ;

use INIT;
use Log ;
use FilesStorage ;
use ZipArchive ;
use Chunks_ChunkDao  ;

class ReviewImproved extends BaseFeature {

    private $feature_options ;

    /**
     * TODO:
     */
    public function filter_cat_job_password( &$path_password ) {
        $filterArgs = array(
            'jid' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT )
        );
        $getInput = (object)filter_input_array( INPUT_GET, $filterArgs );
    }

    /**
     * This filter is to store the review_password in the data strucutre
     * to be passed back to the javascript.
     */
    public function x_filter_manage_projects_loaded( $projects ) {
        $chunks = array();
        foreach( $projects as $project ) {
            foreach( $project['jobs'] as $job ) {
                foreach( array_values($job) as $chunk ) {
                    $chunks[] = array( $chunk['id'], $chunk['password']);
                }
            }
        }

        $chunk_reviews = \LQA\ChunkReviewDao::findReviewPasswordByChunkIds( $chunks );

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
    public function postProjectCreate() {
        $this->feature_options = json_decode( $this->feature->options );

        if ( $this->feature_options->id_qa_model ) {
            $this->setQaModelFromFeatureOptions();
        }
        else {
            $this->setQaModelFromJsonFile();
        }

        $id_job = $this->project_structure['array_jobs']['job_list'][0];
        $this->createQaChunkReviewRecord( $id_job  );
    }

    /**
     * postJobSplitted
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     */
    public function postJobSplitted() {
        $id_job = $this->project_structure['array_jobs']['job_list'][0] ;
        \LQA\ChunkReviewDao::deleteByJobId( $id_job );
        $this->createQaChunkReviewRecord( $id_job );
    }

    /**
     * postJobMerged
     *
     * Deletes the previously created record and creates the new records matching the new chunks.
     * TODO: this action should merge revision data as well.
     */
    public function postJobMerged() {
        $id_job = $this->project_structure['job_to_merge'] ;
        \LQA\ChunkReviewDao::deleteByJobId( $id_job );
        $this->createQaChunkReviewRecord( $id_job );
    }

    /**
     * Entry point for project data validation for this feature.
     */
    public function validateProjectCreation()  {
        $this->feature_options = json_decode( $this->feature->options );

        if ( $this->feature_options->id_qa_model ) {
            // pass
        } else {
            $this->validateModeFromJsonFile();
        }
    }

    /**
     * @param $array_jobs The jobs array coming from the project_structure
     *
     */
    private function createQaChunkReviewRecord( $id_job ) {
        $id_project = $this->project_structure['id_project'];

        $chunks = Chunks_ChunkDao::getByJobIdProjectAndIdJob( $id_project, $id_job ) ;

        foreach( $chunks as $chunk ) {

            $data = array(
                'id_project' => $id_project,
                'id_job'     => $chunk->id,
                'password'   => $chunk->password
            );

            \LQA\ChunkReviewDao::createRecord( $data );
        }
    }

    /**
     * Sets the QA model fom the uploaded file which was previously validated
     * and added to the project structure.
     */
    private function setQaModelFromJsonFile() {
        $model_json = $this->project_structure['features']
            ['review_improved']['__meta']['qa_model'];

        $model_record = \LQA\ModelDao::createModelFromJsonDefinition( $model_json );

        $project = \Projects_ProjectDao::findById(
            $this->project_structure['id_project']
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
    private function setQaModelFromFeatureOptions() {
        $project = \Projects_ProjectDao::findById(
            $this->project_structure['id_project']
        );

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

    private function validateModeFromJsonFile() {
        $projectStructure = $this->project_structure ;

        $fs = new FilesStorage();
        $zip_file = $fs->getTemporaryUploadedZipFile( $projectStructure['uploadToken'] );
        $model_file_path = 'zip://' . $zip_file . '#__meta/qa_model.json' ;
        $qa_model = file_get_contents( $model_file_path ); ;

        if ( $qa_model === FALSE ) {
            $projectStructure['result']['errors'][ ] = array(
                'code' => '-900',  // TODO: decide how to assign such errors
                'message' => 'QA model definition is missing'
            );
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

}
