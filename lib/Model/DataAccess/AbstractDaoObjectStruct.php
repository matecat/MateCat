<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 * 
 */

abstract class DataAccess_AbstractDaoObjectStruct extends stdClass {

    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

    public function toArray(){
        return (array)$this;
    }

} 