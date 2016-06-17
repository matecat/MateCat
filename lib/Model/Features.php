<?php


/**
 * Class Features
 *
 * This class should be replaced by FeatureSet, which will be capable
 * to work with owner based features and environment based features.
 *
 *
 */
class Features {
    
    const PROJECT_COMPLETION   = 'project_completion';
    const TRANSLATION_VERSIONS = 'translation_versions';
    const REVIEW_IMPROVED      = 'review_improved';
    const QACHECK_GLOSSARY     = 'qa_check_glossary';
    const QACHECK_BLACKLIST    = 'qa_check_blacklist';

    public static $VALID_CODES = array(
        Features::PROJECT_COMPLETION,
        Features::TRANSLATION_VERSIONS,
        Features::REVIEW_IMPROVED,
        Features::QACHECK_GLOSSARY,
        Features::QACHECK_BLACKLIST
    );

    /**
     * Returns the filtered subject variable passed to all enabled features.
     *
     * @param $method
     * @param $id_customer
     * @param $filterable
     *
     * @return mixed
     *
     * FIXME: this is not a real filter since the input params are not passed
     * modified in cascade to the next function in the queue.
     */
    public static function filter($method, $id_customer, $filterable) {
        $args = array_slice( func_get_args(), 2);

        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        foreach( $features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;
            // XXX FIXME TODO: find a better way for this initialiation, $projectStructure is not defined
            // here, so the feature initializer should not need the project strucutre at all.
            // The `id_customer` should be enough. XXX

            if ( class_exists( $name ) ) {
                $obj = new $name( $feature );

                if ( method_exists( $obj, $method ) ) {
                    $filterable = call_user_func_array( array( $obj, $method ), $args );
                }
            }
        }

        return $filterable ;

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

            if ( class_exists( $name ) ) {
                $obj = new $name( $feature );

                if ( method_exists( $obj, $method ) ) {
                    \Log::doLog( " calling $name, $method, with args " . var_export( $args, true) );
                    call_user_func_array( array( $obj, $method ), $args );
                }
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
     * @param $project
     *
     * @return bool
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
