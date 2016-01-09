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
     * Invoke the given method on feature-specific classes, if available. Pass the
     * projectStructure as input.
     * @param $method string the method to invoke
     * @param $projectStructure ArrayObject
     */

    public static function run( $method, ArrayObject $projectStructure ) {
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
     * reviewImprovedEnabled
     *
     */

    private static function reviewImprovedEnabled($project) {
        return $project->id_qa_model != null;
    }



}
