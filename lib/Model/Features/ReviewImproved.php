<?php

namespace Features ;

use INIT;
use Log ;
use FilesStorage ;
use ZipArchive ;

class ReviewImproved extends BaseFeature {

    /**
     * Performs post project creation tasks for the current project.
     *
     */
    public function postProjectCreate() {

        $options = json_decode($this->feature->options);

        if ( $options->id_qa_model != null ) {
            $dao = new \Projects_ProjectDao( \Database::obtain() );
            // TODO: continue from here. Reopen the zip file and create the QA model from here.
            $dao->updateField( $this->project, 'id_qa_model', $options->id_qa_model);
        }
    }

    /**
     * Validate the project is valid in the scope of ReviewImproved feature.
     * A project is valid if we area able to find a qa_model.json file inside
     * a __meta folder. The qa_model.json file must be valid too.
     *
     * If validation fails, adds errors to the projectStructure.
     *
     * @param projectStructure the project structure before it's persisted to database.
     *
     */

    public static function validateProjectCreation( & $projectStructure )  {
        \Log::doLog( 'projectStructure', $projectStructure );

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

        // TODO: implement other validations
    }

}
