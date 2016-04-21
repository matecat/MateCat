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
            throw new DomainException( 'Unknown/not-accessible property ' . $name );
        }
    }

    /**
     * This method makes it possible to define methods on child classes
     * whose result is cached on the instance.
     *
     * @param $method_name
     * @param $params
     * @param $function
     *
     * FIXME: current implementation is bogus because it only allows to pass one parameter.
     *
     *
     * @return mixed
     */
    protected function cachable( $method_name, $params, $function ) {
        if ( !array_key_exists( $method_name, $this->cached_results ) ) {
            $this->cached_results[ $method_name ] = call_user_func( $function, $params );
        }

        return $this->cached_results[ $method_name ];
    }

    /**
     * This method returns the same object so to be chainable
     * and be sure to clear the cache when calling cachable
     * methods.
     *
     * @example assuming the model has a cachable
     * method called foo();
     *
     * $model->foo(); // makes computation the first time and caches
     * $model->foo(); // returns the cached result
     * $model->clear()->foo(); // clears the cache and returns fresh data
     *
     * @return $this
     */
    public function clear() {
        $this->cached_results = array();
        return $this;
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