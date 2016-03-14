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
        array_merge( $this->features, $features );
    }

    /**
     * @param $method
     */
    public function run( $method ) {
        $args = array_slice( func_get_args(), 1 );

        foreach ( $this->features as $feature ) {
            $name = "Features\\" . $feature->toClassName();
            $obj  = new $name( $feature );

            if ( method_exists( $obj, $method ) ) {
                \Log::doLog( " calling $name, $method, with args " . var_export( $args, true ) );
                call_user_func_array( array( $obj, $method ), $args );
            }
        }
    }


}