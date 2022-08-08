<?php

use AbstractControllers\IController;
use API\V2\Exceptions\AuthenticationError;
use Exceptions\ValidationError;
use Features\BaseFeature;
use Matecat\SubFiltering\Contracts\FeatureSetInterface;

/**
 * Created by PhpStorm.
 * User: fregini/ostico
 * Date: 3/11/16
 * Time: 11:00 AM
 */
class FeatureSet implements FeatureSetInterface
{
    /**
     * @var BasicFeatureStruct[]
     */
    private $features = [];

    protected $_ignoreDependencies = false;

    /**
     * @return BasicFeatureStruct[]
     */
    public function getFeaturesStructs(){
        return $this->features;
    }

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
            $this->__loadFromMandatory();
        } else {

            $_features = [];
            foreach ( $features as $feature ) {
                if ( property_exists( $feature, 'feature_code' ) ) {
                    $_features[ $feature->feature_code ] = $feature;
                } else {
                    throw new Exception( '`feature_code` property not found on ' . var_export( $feature, true ) );
                }
            }
            $this->merge( $_features );
        }
    }

    /**
     * @return array
     */
    public function getCodes() {
        return array_values( array_map( function ( $feature ) {
            return $feature->feature_code;
        }, $this->features ) );
    }

    /**
     * Finds if any of the features loaded are extending AbstractRevisionFeature.
     *
     * @deprecated This method was introduced for refactoring purpose and should not be used
     *             for new implementations.
     */
    public function hasRevisionFeature() {
        return $this->detectInstanceOf( 'Features\AbstractRevisionFeature' );
    }

    /**
     * This function scans all features' class hierarchy tree and returns the first
     * feature that matches the given input name.
     *
     * This method is useful to find if the featurese has any special base or abstract
     * feature which may require special conditionals.
     *
     * @param $class_name
     *
     * @deprecated This method was introduced for to facilitate refactoring and should not be
     *             used for new implementations.
     *
     * @return bool
     */
    public function detectInstanceOf( $class_name ) {
        foreach ( $this->features as $feature ) {
            $name = Features::getPluginClass( $feature->feature_code );
            if ( $name ) {
                $obj = new $name( $feature );
                if ( in_array( $class_name, class_parents( $obj ) ) ) {
                    return $obj;
                }
            }
        }

        return false;
    }

    /**
     * @param $string
     *
     * @throws Exception
     */
    public function loadFromString( $string ) {
        $this->loadFromCodes( FeatureSet::splitString( $string ) );
    }

    /**
     * @param $feature_codes
     *
     * @throws Exception
     */
    private function loadFromCodes( $feature_codes ) {
        $features = [];

        if ( !empty( $feature_codes ) ) {
            foreach ( $feature_codes as $code ) {
                $features [ $code ] = new BasicFeatureStruct( [ 'feature_code' => $code ] );
            }

            $this->merge( $features );
        }
    }

    /**
     * Load features that should be enabled on project scope.
     *
     * This fetures include
     *
     * 1. the ones explicity defined `project_metadata`;
     * 2. the ones in autoload array that can be forcedly enabled on project.
     *
     * @param Projects_ProjectStruct $project
     *
     * @return void
     * @throws Exception
     */
    public function loadForProject( Projects_ProjectStruct $project ) {
        $this->clear();
        $this->_setIgnoreDependencies( true );
        $this->loadForceableProjectFeatures();
        $this->loadFromCodes(
                FeatureSet::splitString( $project->getMetadataValue( Projects_MetadataDao::FEATURES_KEY ) )
        );
        $this->_setIgnoreDependencies( false );
    }

    protected function _setIgnoreDependencies( $value ) {
        $this->_ignoreDependencies = $value;
    }

    public function clear() {
        $this->features = [];
    }

    /**
     * @param $metadata
     *
     * @throws Exception
     * @throws \Exceptions\NotFoundException
     * @throws ValidationError
     */
    public function loadProjectDependenciesFromProjectMetadata( $metadata ) {
        $project_dependencies = [];
        $project_dependencies = $this->filter( 'filterProjectDependencies', $project_dependencies, $metadata );
        $features             = [];
        foreach ( $project_dependencies as $dependency ) {
            $features [ $dependency ] = new BasicFeatureStruct( [ 'feature_code' => $dependency ] );
        }

        $this->merge( $features );
    }

    /**
     *
     * @param $id_customer
     *
     * @throws Exception
     */
    public function loadFromUserEmail( $id_customer ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );
        $this->merge( $features );
    }

    /**
     * Loads features that can be forced on projects, even if they are not assigned to project explicitly,
     * reading from AUTOLOAD_PLUGINS.
     *
     * @throws Exception
     */
    public function loadForceableProjectFeatures() {
        $returnable = array_filter( $this->__getAutoloadPlugins(), function ( BasicFeatureStruct $feature ) {
            $concreteClass = $feature->toNewObject();

            return $concreteClass->isForceableOnProject();
        } );

        $this->merge( $returnable );
    }

    /**
     * When some HTML page need to load static
     * resources for customization from mandatory plugins
     * even when a plugin is not auto activable for the projects
     * ( Ex: analyze page )
     *
     * @see FeatureSet::loadForProject()
     *
     * @throws Exception
     */
    public function forceAutoLoadFeatures() {
        $this->__loadFromMandatory();
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
     * @throws Exception
     */
    public function loadAutoActivableOwnerFeatures( $id_customer ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );

        $objs = array_map( function ( $feature ) {
            /* @var $feature BasicFeatureStruct */
            return $feature->toNewObject();
        }, $features );

        $returnable = array_filter( $objs, function ( BaseFeature $obj ) {
            return $obj->isAutoActivableOnProject();
        } );

        $this->merge( array_map( function ( BaseFeature $feature ) {
            return $feature->getFeatureStruct();
        }, $returnable ) );
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
     * @throws \Exceptions\NotFoundException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws \TaskRunner\Exceptions\ReQueueException
     * @throws \TaskRunner\Exceptions\EndQueueException
     */
    public function filter( $method, $filterable ) {
        $args = array_slice( func_get_args(), 1 );

        foreach ( $this->features as $feature ) {
            /* @var $feature BasicFeatureStruct */
            $obj = $feature->toNewObject();

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
                        $filterable = call_user_func_array( [ $obj, $method ], $args );
                    } catch ( ValidationError $e ) {
                        throw $e;
                    } catch ( \Exceptions\NotFoundException $e ) {
                        throw $e;
                    } catch ( AuthenticationError $e ) {
                        throw $e;
                    } catch ( \TaskRunner\Exceptions\ReQueueException $e ) {
                        throw $e;
                    } catch ( \TaskRunner\Exceptions\EndQueueException $e ) {
                        throw $e;
                    } catch ( Exception $e ) {
                        Log::doJsonLog( "Exception running filter " . $method . ": " . $e->getMessage() );
                    }
                }
            }
        }

        return $filterable;
    }


    /**
     * @param $method
     *
     * @throws Exception
     */
    public function run( $method ) {
        $args       = array_slice( func_get_args(), 1 );
        $returnable = [];

        foreach ( $this->features as $feature ) {
            $returnable[ $feature->feature_code ] = $this->runOnFeature( $method, $feature, $args );
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
     * @param string      $name       name of the decorator to activate
     * @param IController $controller the controller to work on
     * @param PHPTAL      $template   the PHPTAL view to add properties to
     *
     * @throws Exception
     */
    public function appendDecorators( $name, IController $controller, PHPTAL $template ) {

        /** @var BasicFeatureStruct $feature */
        foreach ( $this->features as $feature ) {

            $cls = Features::getFeatureClassDecorator( $feature, $name );
            if ( !empty( $cls ) ) {
                /** @var AbstractDecorator $obj */
                $obj = new $cls( $controller, $template );
                $obj->decorate();
            }

        }

    }

    /**
     * This function ensures that whenever a plugin load is requested
     * it's own dependencies are also loaded
     *
     * These dependencies are ordered so the plugin is every time at the last position
     *
     * @throws Exception
     */
    public function sortFeatures() {


        $toBeSorted     = array_values( $this->features );
        $sortedFeatures = $this->_quickSort( $toBeSorted );

        $this->clear();
        foreach ( $sortedFeatures as $value ) {
            $this->features[ $value->feature_code ] = $value;
        }

        return $this;

    }

    /**
     * Warning Recursion, memory overflow if there are a lot of features ( but this is impossible )
     *
     * @param BasicFeatureStruct[]
     *
     * @return BasicFeatureStruct[]
     */
    private function _quickSort( $featureStructsList ) {

        $length = count( $featureStructsList );
        if ( $length < 2 ) {
            return $featureStructsList;
        }

        /**
         * @var $firstInList BasicFeatureStruct
         */
        $firstInList        = $featureStructsList[ 0 ];
        $ObjectFeatureFirst = $firstInList->toNewObject();

        $leftBucket = $rightBucket = [];

        for ( $i = 1; $i < $length; $i++ ) {

            if ( in_array( $featureStructsList[ $i ]->feature_code, $ObjectFeatureFirst::getDependencies() ) ) {
                $leftBucket[] = $featureStructsList[ $i ];
            } else {
                $rightBucket[] = $featureStructsList[ $i ];
            }

        }

        return array_merge( $this->_quickSort( $leftBucket ), [ $firstInList ], $this->_quickSort( $rightBucket ) );

    }

    /**
     * Foe each feature Load it's defined dependencies
     * @throws Exception
     */
    private function _loadFeatureDependencies() {

        $codes = $this->getCodes();
        foreach ( $this->features as $feature ) {
            /**
             * @var $feature BasicFeatureStruct
             */
            $baseFeature          = $feature->toNewObject();
            $missing_dependencies = array_diff( $baseFeature::getDependencies(), $codes );

            if ( !empty( $missing_dependencies ) ) {
                foreach ( $missing_dependencies as $code ) {
                    $this->features [ $code ] = new BasicFeatureStruct( [ 'feature_code' => $code ] );
                }
            }

        }

    }

    /**
     * Updates the features array with new features. Ensures no duplicates are created.
     * Loads dependencies as needed.
     *
     * @param $new_features BasicFeatureStruct[]
     *
     * @throws Exception
     */
    private function merge( $new_features ) {

        if ( !$this->_ignoreDependencies ) {
            $this->_loadFeatureDependencies();
        }

        $all_features    = [];
        $conflictingDeps = [];

        foreach ( $new_features as $feature ) {
            // flat dependency management

            $baseFeature = $feature->toNewObject();

            $conflictingDeps[ $feature->feature_code ] = $baseFeature::getConflictingDependencies();

            $deps = [];

            if ( !$this->_ignoreDependencies ) {
                $deps = array_map( function ( $code ) {
                    return new BasicFeatureStruct( [ 'feature_code' => $code ] );
                }, $baseFeature->getDependencies() );
            }

            $all_features = array_merge( $all_features, $deps, [ $feature ] );
        }

        /** @var BasicFeatureStruct $feature */
        foreach ( $all_features as $feature ) {
            foreach ( $conflictingDeps as $key => $value ) {
                if ( empty( $conflictingDeps[ $key ] ) ) {
                    continue;
                }
                if ( in_array( $feature->feature_code, $value ) ) {
                    throw new Exception( "{$feature->feature_code} is conflicting with $key." );
                }
            }
            if ( !isset( $this->features[ $feature->feature_code ] ) ) {
                $this->features[ $feature->feature_code ] = $feature;
            }
        }

        $this->features = $this->filter( 'filterFeaturesMerged', $this->features );
        $this->sortFeatures();

    }

    public static function splitString( $string ) {
        return array_filter( explode( ',', trim( $string ) ) );
    }

    /**
     * Loads plugins into the FeatureSet from the list of mandatory plugins.
     *
     * @return void
     *
     * @throws Exception
     */
    private function __loadFromMandatory() {
        $features = $this->__getAutoloadPlugins();
        $this->merge( $features );
    }

    /**
     * @return array
     */
    private function __getAutoloadPlugins() {
        $features = [];

        if ( !empty( INIT::$AUTOLOAD_PLUGINS ) ) {
            foreach ( INIT::$AUTOLOAD_PLUGINS as $plugin ) {
                $features[ $plugin ] = new BasicFeatureStruct( [ 'feature_code' => $plugin ] );
            }
        }

        return $features;
    }

    /**
     * Runs a command on a single feautre
     *
     * @param $method
     * @param $feature
     * @param $args
     *
     * @return mixed
     */
    private function runOnFeature( $method, BasicFeatureStruct $feature, $args ) {
        $name = Features::getPluginClass( $feature->feature_code );
        if ( $name ) {
            $obj = new $name( $feature );

            if ( method_exists( $obj, $method ) ) {
                return call_user_func_array( [ $obj, $method ], $args );
            }
        }
    }

}