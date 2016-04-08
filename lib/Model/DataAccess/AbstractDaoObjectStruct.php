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

    /**
     * Returns an array of the public attributes of the struct.
     * If $mask is provided, the resulting array will include
     * only the specified keys.
     *
     * This method is useful in conjunction with PDO execute, where only
     * a subset of the attributes may be required to be bound to the query.
     *
     * @param $mask array|null a mask for the keys to return
     * @return array
     */
    public function toArray( $mask = null ){

        $attributes = array();
        $reflectionClass = new ReflectionClass( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC ) ;
        foreach( $publicProperties as $property ) {
            if ( !empty($mask) ) {
                if (! in_array( $property->getName(), $mask)) {
                    continue;
                }
            }
            $attributes[$property->getName()] = $property->getValue($this);
        }
        return $attributes;

    }

} 