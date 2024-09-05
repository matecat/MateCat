<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */

abstract class DataAccess_AbstractDaoObjectStruct extends stdClass implements DataAccess_IDaoStruct, Countable {

    protected array $cached_results = [];

    public function __construct( array $array_params = [] ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    /**
     * @param $name
     * @param $value
     *
     * @return void
     * @throws DomainException
     */
    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

    /**
     * This method returns the same object so to be chainable
     * and be sure to clear the cache when calling cachable
     * methods.
     *
     * @return $this
     * @example assuming the model has a cachable
     *          method called foo();
     *
     * $model->foo(); // makes computation the first time and caches
     * $model->foo(); // returns the cached result
     * $model->clear()->foo(); // clears the cache and returns fresh data
     *
     */
    public function clear(): DataAccess_AbstractDaoObjectStruct {
        $this->cached_results = [];

        return $this;
    }

    /**
     * This method makes it possible to define methods on child classes
     * whose result is cached on the instance.
     *
     * @param $method_name
     * @param $params
     * @param $function
     *
     * @return mixed
     *
     */
    protected function cachable( $method_name, $params, $function ) {
        $resultset = $this->cached_results[ $method_name ] ?? null;
        if ( $resultset == null ) {
            $resultset = $this->cached_results[ $method_name ] = call_user_func( $function, $params );
        }

        return $resultset;
    }

    /**
     * @param $name
     *
     * @return mixed
     * @throws DomainException
     */
    public function __get( $name ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Trying to get an undefined property ' . $name );
        }

        return $this->$name;
    }

    public function setTimestamp( $attribute, $timestamp ) {
        $this->$attribute = date( 'c', $timestamp );
    }

    /**
     * Returns an array of public attributes for the struct.
     * If $mask is provided, the resulting array will include
     * only the specified keys.
     *
     * This method is useful in conjunction with PDO execute, where only
     * a subset of the attributes may be required to be bound to the query.
     *
     * @param $mask ?array a mask for the keys to return
     *
     * @return array
     *
     */
    public function toArray( $mask = null ) {

        $attributes       = [];
        $reflectionClass  = new ReflectionObject( $this );
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

    /**
     * Compatibility with ArrayObject
     *
     * @return array
     */
    public function getArrayCopy() {
        return $this->toArray();
    }

    public function count(): int {
        $reflectionClass = new ReflectionObject( $this );

        return count( $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC ) );
    }


} 