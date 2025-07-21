<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */

namespace Model\DataAccess;

use Countable;
use DomainException;
use ReflectionObject;
use ReflectionProperty;
use stdClass;

abstract class AbstractDaoObjectStruct extends stdClass implements IDaoStruct, Countable {

    use RecursiveArrayCopy;

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
    public function clear(): AbstractDaoObjectStruct {
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
    protected function cachable( string $method_name, $params, callable $function ) {
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