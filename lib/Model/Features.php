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
        self::evalMethodOnFeatures('validateProjectCreation', $projectStructure);
    }


    /**
     * processProjectCreated
     *
     * TODO: to be improved. This should be the entry point to apply
     * feature specific updated to the project due to owner features.
     */

    public static function processProjectCreated( ArrayObject $projectStructure ) {
        self::evalMethodOnFeatures('postProjectCreate', $projectStructure);
    }

    /**
     * processJobsCreated
     *
     */

    public static function processJobsCreated( ArrayObject $projectStructure ) {
        self::evalMethodOnFeatures('postJobsCreated', $projectStructure);
    }

    private static function evalMethodOnFeatures( $method, ArrayObject $projectStructure ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $projectStructure['id_customer'] );

        foreach( $features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;
            $cls = new $name( $projectStructure, $feature );

            if ( method_exists( $cls, $method ) ) {
                $cls->$method();
            }
        }
    }

    /**
     * reviewImprovedEnabled
     *
     */

    private static function reviewImprovedEnabled($project) {
        return $project->id_qa_model != null;
    }



}
