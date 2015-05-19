<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/05/15
 * Time: 18.12
 */

abstract class DQF_DqfAbstractStruct extends stdClass{

    /**
     * API version
     * @var string
     */
    public $v;

    public $app;

    public $app_version;

    public $type;

    /**
     * @param $struct
     */
    public function __construct( $struct ) {
        foreach ( $struct as $prop => $value ) {
            if ( $prop == "payload" ) {
                foreach ( $value as $payload_prop => $payload_value ) {
                    $this->{$payload_prop} = $payload_value;
                }
            }
            else {
                $this->{$prop} = $value;
            }
        }

    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }
}