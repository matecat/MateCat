<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/11/16
 * Time: 11:00 AM
 */
class FeatureSet {

    private $features = array();

    /**
     * @param array $features
     */
    public function __construct( array $features = array() ) {
        $this->features = $features;
        $this->loadFromMandatory();
    }

    /**
     * @param $id_customer
     *
     * @return FeatureSet
     */
    public static function fromIdCustomer( $id_customer ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );
        return new FeatureSet($features);
    }

    /**
     * @param $id_customer
     */
    public function loadFromIdCustomer( $id_customer ) {
        $features = OwnerFeatures_OwnerFeatureDao::getByIdCustomer( $id_customer );
        $this->features = array_merge( $this->features, $features );
    }

    /**
     * @param array $params
     */
    public function loadFeatures( $params = array() ) {
       if ( array_key_exists('id_customer', $params) ) {
            $this->loadFromIdCustomer( $params['id_customer'] ) ;
        }
    }

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
    public function filter($method, $filterable) {
        $args = array_slice( func_get_args(), 1);

        foreach( $this->features as $feature ) {
            $name = "Features\\" . $feature->toClassName() ;
            // XXX FIXME TODO: find a better way for this initialiation, $projectStructure is not defined
            // here, so the feature initializer should not need the project strucutre at all.
            // The `id_customer` should be enough. XXX
            if ( class_exists( $name ) ) {
                $obj = new $name( $feature );

                if ( method_exists( $obj, $method ) ) {
                    array_shift( $args );
                    array_unshift( $args, $filterable );

                    $filterable = call_user_func_array( array( $obj, $method ), $args );
                }
            }
        }

        return $filterable ;
    }

    /**
     * @param $method
     */
    public function run( $method ) {
        $args = array_slice( func_get_args(), 1 );

        foreach ( $this->features as $feature ) {
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
     * @param $name name of the decorator to activate
     * @param viewController $controller the controller to work on
     * @param PHPTAL $template the PHPTAL view to add properties to
     *
     */
    public function appendDecorators($name, viewController $controller, PHPTAL $template) {
        foreach( $this->features as $feature ) {

            $baseClass = "Features\\" . $feature->toClassName()  ;

            $cls =  "$baseClass\\Decorator\\$name" ;

            // XXX: keep this log line because due to a bug in Log class
            // if this line is missing it won't log load errors.
            Log::doLog('loading Decorator ' . $cls );

            if ( class_exists( $cls ) ) {
                $obj = new $cls( $controller, $template ) ;
                $obj->decorate();
            }

        }

    }

    /**
     * Loads plugins into the featureset from the list of mandatory plugins.
     */
    private function loadFromMandatory() {
        $features = [] ;
        foreach( INIT::$MANDATORY_PLUGINS as $plugin) {
            $features[] = new BasicFeatureStruct(array('feature_code' => $plugin));
        }
        $this->features = array_merge($this->features, $features);
    }

    /**
     * Runs a command on a single feautre
     *
     * @param $method
     * @param $feature
     * @param $args
     */
    private function runOnFeature($method, BasicFeatureStruct $feature, $args)
    {
        $name = "Features\\" . $feature->toClassName();

        if (class_exists($name)) {
            $obj = new $name($feature);

            if (method_exists($obj, $method)) {
                call_user_func_array(array($obj, $method), $args);
            }
        }
    }

}