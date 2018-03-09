<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.02
 */

abstract class Engines_Results_AbstractResponse {

    public $responseStatus = "";
    public $responseDetails = "";
    public $responseData = "";

    /**
     * @var \Engines_Results_ErrorMatches
     */
    public $error;

    protected $_rawResponse = "";

    public static function getInstance( $result ){

        $class = get_called_class(); // late static binding, note: php >= 5.3
        $instance = new $class( $result );

        if ( is_array( $result ) and array_key_exists( "error", $result ) ) {
            $instance->error = new Engines_Results_ErrorMatches( $result[ 'error' ] );
        }

        return $instance;

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
     *
     * @return array
     * @throws ReflectionException
     */
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