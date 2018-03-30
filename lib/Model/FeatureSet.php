<?php

use AbstractControllers\IController;
use Exceptions\ValidationError;
use Features\BaseFeature;
use Features\Dqf;
use Features\IBaseFeature;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 11:00 AM
 */
class FeatureSet {

    private $features = array();

    /**
     * Initializes a new FeatureSet. If $features param is provided, FeaturesSet is populated with the given params.
     * Otherwise it is populated with mandatory features.
     *
     * @param $features
     *
     * @throws Exception
     */
    public function __construct( $features = null ) {
        if ( is_null( $features ) ) {
            $this->features = self::__loadFromMandatory() ;
        } else {
            if ( !is_array( $features ) ) {
                throw new Exception('features params should be an array');
            }
            $this->features = $features ;
        }
    }

    public function getCodes() {
        return array_values( array_map( function( $feature ) { return $feature->feature_code ; }, $this->features) );
    }

    public function loadFromString( $string ) {
        $this->loadFromCodes( FeatureSet::splitString( $string ) ) ;
    }

    private function loadFromCodes( $feature_codes ) {
        $features = array();

        if ( !empty( $feature_codes ) ) {
            foreach( $feature_codes as $code ) {
                $features [] = new BasicFeatureStruct( array( 'feature_code' => $code ) );
            }
            $this->features = static::merge($this->features, $features);
        }
    }

    /**
     * Features are attached to project via project_metadata.
     *
     * @param Projects_ProjectStruct $project
     *
     * @return FeatureSet
     */
     public static function loadForProject( Projects_ProjectStruct $project ) {
         $featureSet = new FeatureSet();
         $featureSet->clear();
         $featureSet->loadAutoActivableMandatoryFeatures();
         $featureSet->loadFromString($project->getMetadataValue( Projects_MetadataDao::FEATURES_KEY  ) );
         return $featureSet ;
    }

    public function clear() {
         $this->features = [];
    }

    /**
     * @param $metadata
     */
    public function loadProjectDependenciesFromProjectMetadata( $metadata ) {
        $project_dependencies = [];
        $project_dependencies = $this->filter('filterProjectDependencies', $project_dependencies, $metadata );
        $features = [] ;
        foreach( $project_dependencies as $dependency ) {
            $features [] = new BasicFeatureStruct( array( 'feature_code' => $dependency ) );
        }
        $this->features = static::merge( $this->features, $features );
    }

    /**
     *
     * @param $id_customer
     */
    public function loadFromUserEmail( $id_customer ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );
        $this->features = static::merge( $this->features, $features );
    }

    /**
     * Loads featurs that can be acivated automatically on project, reading from
     * the list of MANDATORY_PLUGIN ( config.ini )
     */
    public function loadAutoActivableMandatoryFeatures() {
        $features = self::__loadFromMandatory();

        $returnable =  array_filter($features, function( BasicFeatureStruct $feature) {
            $concreteClass = self::getObj( $feature ) ;
            return $concreteClass->isAutoActivableOnProject();
        }) ;

        $this->features = static::merge( $this->features, array_map( function( BaseFeature $feature ) {
            return $feature->getFeatureStruct();
        }, $returnable ) ) ;
    }

    /**
     * Loads features that can be activated automatically on proejct, i.e. those that
     * don't require a parameter to be passed from the UI.
     *
     * This functions does some transformation in order to leverage `autoActivateOnProject()` function
     * which is defined on the concrete feature class.
     *
     * So it does the following:
     *
     * 1. find all owner_features for the given user
     * 2. instantiate a concrete feature class for each record
     * 3. filter the list based on the return of autoActivateOnProject()
     * 4. populate the featureSet with the resulting OwnerFeatures_OwnerFeatureStruct
     *
     * @param $id_customer
     *
     * @return array
     */
    public function loadAutoActivableOwnerFeatures( $id_customer ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        $objs = array_map( function( $feature ) {
            return self::getObj( $feature );
        }, $features ) ;

        $returnable =  array_filter($objs, function( BaseFeature $obj ) {
            return $obj->isAutoActivableOnProject();
        }) ;

        $this->features = static::merge( $this->features, array_map( function( BaseFeature $feature ) {
            return $feature->getFeatureStruct();
        }, $returnable ) ) ;
    }

    /**
     * Returns the filtered subject variable passed to all enabled features.
     *
     * @param $method
     * @param $filterable
     *
     * @return mixed
     *
     * FIXME: this is not a real filter since the input params are not passed
     * modified in cascade to the next function in the queue.
     * @throws Exceptions_RecordNotFound
     * @throws ValidationError
     * @internal param $id_customer
     */
    public function filter($method, $filterable) {
        $args = array_slice( func_get_args(), 1);

        foreach( $this->sortFeatures()->features as $feature ) {
            $obj = self::getObj( $feature );

            if ( !is_null( $obj ) ) {
                if ( method_exists( $obj, $method ) ) {
                    array_shift( $args );
                    array_unshift( $args, $filterable );

                    try {
                        /**
                         * There may be the need to avoid a filter to be executed before or after other ones.
                         * To solve this problem we could always pass last argument to call_user_func_array which
                         * contains a list of executed feature codes.
                         *
                         * Example: $args + [ $executed_features ]
                         *
                         * This way plugins have the chance to decide wether to change the value, throw an exception or
                         * do whatever they need to based on the behaviour of the other features.
                         *
                         */
                        $filterable = call_user_func_array( array( $obj, $method ), $args );
                    } catch ( ValidationError $e ) {
                        throw $e ;
                    } catch ( Exceptions_RecordNotFound $e ) {
                        throw $e ;
                    } catch ( Exception $e ) {
                        Log::doLog("Exception running filter " . $method . ": " . $e->getMessage() );
                    }
                }
            }
        }

        return $filterable ;
    }

    /**
     * @param $feature
     *
     * @return null|IBaseFeature
     */
    public static function getObj( $feature ) {
        /* @var $feature BasicFeatureStruct */
        $name = "Features\\" . $feature->toClassName() ;

        if ( class_exists( $name ) ) {
            return new $name( $feature );
        } else {
            return null ;
        }
    }

    /**
     * @param $method
     */
    public function run( $method ) {
        $args = array_slice( func_get_args(), 1 );

        foreach ( $this->sortFeatures()->features as $feature ) {
            $this->runOnFeature($method, $feature, $args);
        }
    }

    /**
     * appendDecorators
     *
     * Loads feature specific decorators, if any is found.
     *
     * Also, gives a last chance to plugins to define a custom decorator class to be
     * added to any call.
     *
     * @param string $name name of the decorator to activate
     * @param IController $controller the controller to work on
     * @param PHPTAL $template the PHPTAL view to add properties to
     *
     */
    public function appendDecorators($name, IController $controller, PHPTAL $template) {
        /** @var BasicFeatureStruct $feature */
        foreach( $this->sortFeatures()->features as $feature ) {

            $baseClass = "Features\\" . $feature->toClassName()  ;

            $cls =  "$baseClass\\Decorator\\$name" ;

            // XXX: keep this log line because due to a bug in Log class
            // if this line is missing it won't log load errors.
            Log::doLog('loading Decorator ' . $cls );

            if ( class_exists( $cls ) ) {
                /** @var AbstractDecorator $obj */
                $obj = new $cls( $controller, $template ) ;
                $obj->decorate();
            }
        }
    }


    /**
     * This function ensures that whenever DQF is present, dependent features always come before.
     * TODO: conver into something abstract.
     */
    public function sortFeatures() {
        $codes = $this->getCodes() ;

        if ( in_array( Dqf::FEATURE_CODE, $codes  )  ) {
            $missing_dependencies = array_diff( Dqf::$dependencies, $codes ) ;
            $this->loadFromCodes( $missing_dependencies );

           usort( $this->features, function( BasicFeatureStruct $left, BasicFeatureStruct $right ) {
               if ( in_array( $left->feature_code, DQF::$dependencies ) ) {
                   return 0 ;
               }
               else {
                   return 1 ;
               }
           });
        }

        return $this ;
    }

    /**
     * Returns an array of feature object instances, merging two input array,
     * ensuring no duplicates are present.
     *
     * @param $left
     * @param $right
     *
     * @return array
     */
    public static function merge( $left, $right ) {
        $returnable = array();

        foreach( $left as $feature ) {
            $returnable[ $feature->feature_code ] = $feature ;
        }

        foreach( $right as $feature ) {
            if ( !isset( $returnable[ $feature->feature_code ] ) ) {
                $returnable[ $feature->feature_code ] = $feature ;
            }
        }

        return $returnable ;
    }

    public static function splitString( $string ) {
        return array_filter( explode(',', trim( $string ) ) ) ;
    }

    /**
     * Loads plugins into the FeatureSet from the list of mandatory plugins.
     *
     * @return BasicFeatureStruct[]|array
     *
     */
    private static function __loadFromMandatory() {
        $features = [] ;

        if ( !empty( INIT::$MANDATORY_PLUGINS ) )  {
            foreach( INIT::$MANDATORY_PLUGINS as $plugin ) {
                $features[] = new BasicFeatureStruct(['feature_code' => $plugin ] );
            }
        }
        return $features ;
    }

    /**
     * Runs a command on a single feautre
     *
     * @param $method
     * @param $feature
     * @param $args
     */
    private function runOnFeature($method, BasicFeatureStruct $feature, $args) {
        $name = self::getClassName( $feature->feature_code );

        if ( $name ) {
            $obj = new $name($feature);

            if (method_exists($obj, $method)) {
                call_user_func_array(array($obj, $method), $args);
            }
        }
    }

    public static function getClassName( $code ) {
        $className = '\Features\\' . Utils::underscoreToCamelCase( $code );
        if ( class_exists( $className ) ) {
            return $className;
        }
        else {
            return false ;
        }
    }

}