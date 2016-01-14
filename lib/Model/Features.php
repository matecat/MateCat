<?php

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
     * appendDecorators
     *
     * Loads feature specific decorators, if any
     *
     * @param $id_customer string Id customer to find active features
     * @param $name name of the decorator to activate
     * @param $controller the controller to work on
     * @param $template the PHPTAL view to add properties to
     *
     */
    public static function appendDecorators($id_customer, $name, viewController $controller, PHPTAL $template) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        foreach( $features as $feature ) {
            $cls = "Features\\" . $feature->toClassName() . "\\$name" ;

            if ( class_exists( $cls ) ) {
                $obj = new $cls( $controller, $template) ;
                $obj->decorate();
            }
        }
    }

    /**
     * Returns the filtered subject variable passed to all enabled features.
     *
     * @param $method, the filter method to invoke.
     * @param $id_customer the customer id to decide if the feature is anabled or not
     * @param $subject the subject variable to be filtered.
     *
     */

    public static function filter() {
        list( $method, $id_customer ) = array_slice( func_get_args(), 0, 2);

        $args = array_slice( func_get_args(), 2);
        $returnable = $args ;

        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        foreach( $features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;
            // XXX FIXME TODO: find a better way for this initialiation, $projectStructure is not defined
            // here, so the feature initializer should not need the project strucutre at all.
            // The `id_customer` should be enough. XXX
            $obj = new $name( null, $feature );

            if ( method_exists( $obj, $method ) ) {
                $returnable = call_user_func_array( array( $obj, $method ), $args );
            }
        }

        return $returnable ;

    }

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
            $obj = new $name( $projectStructure, $feature );

            if ( method_exists( $obj, $method ) ) {
                $obj->$method();
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
