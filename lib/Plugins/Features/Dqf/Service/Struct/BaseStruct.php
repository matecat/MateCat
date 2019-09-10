<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/02/2017
 * Time: 12:10
 */

namespace Features\Dqf\Service\Struct;

use ReflectionClass;
use ReflectionProperty;

abstract class BaseStruct {

    public function __construct( $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function toArray( $mask = null ){
        $attributes = array();
        $reflectionClass = new ReflectionClass( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC ) ;
        foreach( $publicProperties as $property ) {
            if ( !empty($mask) ) {
                if ( !in_array( $property->getName(), $mask ) ) {
                    continue;
                }
            }
            $attributes[ $property->getName() ] = $property->getValue( $this );
        }
        return $attributes;
    }


}