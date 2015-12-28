<?php

include 'Features/ReviewImproved.php';
include 'Features/TranslationVersions.php';

class Features {
    const PROJECT_COMPLETION = 'project_completion' ;
    const TRANSLATION_VERSIONS = 'translation_versions'  ;
    const REVIEW_IMPROVED = 'review_improved' ;

    public static $VALID_CODES = array(
        Features::PROJECT_COMPLETION,
        Features::TRANSLATION_VERSIONS,
        Features::REVIEW_IMPROVED
    );

    /**
     * Some features require additional checks in order to define
     * if they are 'enabled' or not.
     */
    public static function enabled($feature, $project) {
        if ( $feature->feature_code == Features::REVIEW_IMPROVED ) {
            return self::reviewImprovedEnabled( $project );
        }
        else {
            return !!$feature ;
        }
    }

    /**
     * Populates the projectStructure with error messages coming from
     * all the features enabled for the current request.
     *
     * @param $projectStructure is the project structure right before it's persisted.
     */

    public static function validateProjectCreation( ArrayObject &$projectStructure ) {
        // find the features to work on. We must rely on the project owner
        //
        \Log::doLog( 'id_customer', $projectStructure['id_customer'] );

        // find the features enabled for the customer
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $projectStructure['id_customer'] );

        foreach( $features as $feature ) {
            \Log::doLog( 'feature', $feature);
            $name = "Features\\" . $feature->toClassName() ;
            $name::validateProjectCreation( $projectStructure );

        }

    }

    // TODO: to be improved. This should be the entry point to apply
    // feature specific updated to the project due to owner features.
    //
    public static function processProjectCreated( $id_project ) {
        // find owner features
        $project = Projects_ProjectDao::findById( $id_project );
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $project->id_customer );

        foreach( $features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;

            $cls = new $name( $project, $feature );
            $cls->postProjectCreate();
        }
    }

    private static function reviewImprovedEnabled($project) {
        return $project->id_qa_model != null;
    }

}
