<?php

namespace Features ;

use INIT;
use Log ;
use FilesStorage ;
use ZipArchive ;

class ReviewImproved extends BaseFeature {

    private $feature_options ;

    /**
     * Performs post project creation tasks for the current project.
     * Evaluates if a qa model is present in the feature options.
     * If so, then try to assign the defined qa_model.
     * If not, then try to find the qa_model from the project structure.
     */
    public function postProjectCreate() {
        $this->feature_options = json_decode( $this->feature->options );

        \Log::doLog( 'feature_options', $this->feature_options );

        if ( $this->feature_options->id_qa_model ) {
            $this->setQaModelFromFeatureOptions();
        }
        else {
            $this->setQaModelFromJsonFile();
        }

        $this->createQaJobReviewRecord();
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
     * createQaJobReviewRecord
     *
     */
    private function createQaJobReviewRecord() {
        \Log::doLog( $this->project_structure['array_jobs']['job_list'] );
        foreach( $this->project_structure['array_jobs']['job_list'] as $k => $v ) {

            $id_job = $v ;
            $password = $this->project_structure['array_jobs']['job_pass'][$k];
            \Log::doLog( $id, $pass );

            $data = array(
                'id_project' => $this->project_structure['id_project'],
                'id_job' => $id_job,
                'password' => $password
            );

            \LQA\JobReviewDao::createRecord( $data );
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
