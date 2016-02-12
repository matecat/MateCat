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
     * @param viewController $controller the controller to work on
     * @param PHPTAL $template the PHPTAL view to add properties to
     *
     */

    public static function appendDecorators($id_customer, $name, viewController $controller, PHPTAL $template) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        foreach( $features as $feature ) {
            $cls = "Features\\" . $feature->toClassName() . "\\Decorator\\$name" ;

            // XXX: keep this log line because due to a bug in Log class
            // if this line is missing it won't log load errors.
            Log::doLog('loading Decorator ' . $cls );

            if ( class_exists( $cls ) ) {
                $obj = new $cls( $controller, $template) ;
                $obj->decorate();
            }
        }
    }

    /**
     * Returns the filtered subject variable passed to all enabled features.
     *
     * @param $method
     * @param $id_customer
     *
     * @return mixed
     *
     * FIXME: this is not a real filter since the input params are not passed
     * modified to the next function in the queue.
     */
    public static function filter($method, $id_customer) {

        $args = array_slice( func_get_args(), 2);
        $returnable = $args[0];

        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        foreach( $features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;
            // XXX FIXME TODO: find a better way for this initialiation, $projectStructure is not defined
            // here, so the feature initializer should not need the project strucutre at all.
            // The `id_customer` should be enough. XXX
            $obj = new $name( $feature );

            if ( method_exists( $obj, $method ) ) {
                $returnable = call_user_func_array( array( $obj, $method ), $args );
            }
        }

        return $returnable ;

    }

    /**
     * Invoke the given method on feature-specific classes, if available. Pass the
     * projectStructure as input.
     *
     * This method is similar to filter, but it's not expected to return an updated
     * input value.
     *
     * @param $method string the method to invoke
     * @param $id_customer string of the customer to search active features for
     *
     */

    public static function run($method, $id_customer) {
        $args = array_slice( func_get_args(), 2);

        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        foreach( $features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;
            $obj = new $name( $feature );

            if ( method_exists( $obj, $method ) ) {
                \Log::doLog( " calling $name, $method, with args " . var_export( $args, true) );
                call_user_func_array( array( $obj, $method ), $args );
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

    /**
     * Give your plugins the possibilty to install routes
     *
     */
    public static function loadRoutes( \Klein\Klein $klein ) {
        list( $null, $prefix, $class_name) = explode('/', $_SERVER['REQUEST_URI']);

        if ( $prefix  == 'plugins' ) {
            $cls = '\\Features\\' .  Utils::underscoreToCamelCase( $class_name );

            if ( class_exists( $cls ) ) {
                $klein->with("/$prefix/$class_name", function() use ($cls, $klein) {
                    $cls::loadRoutes( $klein );
                });
            }
        }

    }

}
