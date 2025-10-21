<?php

namespace Utils\Engines\Results;

use Model\FeaturesBase\FeatureSet;
use ReflectionClass;
use ReflectionProperty;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.02
 */
abstract class TMSAbstractResponse {

    public int $responseStatus = 200;

    /**
     * @var string|array
     */
    public $responseDetails = "";

    /**
     * @var mixed
     */
    public      $responseData    = [];
    public bool $mtLangSupported = true;

    /**
     * @var ErrorResponse|null
     */
    public ?ErrorResponse $error = null;

    protected string $_rawResponse = "";

    /**
     * @var ?FeatureSet
     */
    protected ?FeatureSet $featureSet = null;

    /**
     * @template T of TMSAbstractResponse
     * @param array|int       $result
     * @param FeatureSet|null $featureSet
     * @param array|null      $dataRefMap
     *
     * @return T
     */
    public static function getInstance( $result, ?FeatureSet $featureSet = null, ?array $dataRefMap = [] ): TMSAbstractResponse {

        /**
         * @var class-string<T> $class
         */
        $class = get_called_class(); // late static binding, note: php >= 5.3

        /**
         * @var T $instance
         */
        $instance = new $class( $result, $dataRefMap );



        if ( is_array( $result ) && isset($result[ 'responseStatus' ]) && $result[ 'responseStatus' ] >= 400 ) {
            $instance->error = new ErrorResponse( $result[ 'error' ] ?? $result[ 'responseDetails' ] );
        }

        if ( $featureSet !== null ) {
            $instance->featureSet( $featureSet );
        }

        return $instance;

    }

    public function featureSet( FeatureSet $featureSet ) {
        $this->featureSet = $featureSet;
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
     */
    public function toArray( ?array $mask = [] ): array {

        $attributes       = [];
        $reflectionClass  = new ReflectionClass( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC );
        foreach ( $publicProperties as $property ) {
            if ( !empty( $mask ) ) {
                if ( !in_array( $property->getName(), $mask ) ) {
                    continue;
                }
            }
            $attributes[ $property->getName() ] = $property->getValue( $this );
        }

        return $attributes;

    }

} 