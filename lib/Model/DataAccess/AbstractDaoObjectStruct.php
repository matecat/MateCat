<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 * 
 */

abstract class DataAccess_AbstractDaoObjectStruct extends stdClass implements DataAccess_IDaoStruct, Countable {

    protected $validator;
    protected $cached_results = array();

    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
        $this->tryValidator();
    }

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
     * This method makes it possible to define methods on child classes
     * whose result is cached on the instance.
     *
     * @param $method_name
     * @param $params
     * @param $function
     *
     * @return mixed
     * 
     * FIXME: current implementation is bogus because it only allows to pass one parameter.
     *
     */
    protected function cachable($method_name, $params, $function) {
        $resultset = isset( $this->cached_results[ $method_name ] ) ? $this->cached_results[ $method_name ] : null;
        if( $resultset == null ){
            $resultset = $this->cached_results[ $method_name ] = call_user_func($function, $params);
        }
        return $resultset;
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get( $name ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Trying to get an undefined property ' . $name );
        }

        return $this->$name;
    }

    public function setTimestamp($attribute, $timestamp) {
        $this->$attribute = date('c', $timestamp);
    }

    /**
     * Checks if any error is present and if so throws an exception
     * with imploded error messages.
     *
     * @throws \Exceptions\ValidationError
     */

    public function ensureValid() {
        if ( !$this->isValid() ) {
            throw new \Exceptions\ValidationError (
                $this->validator->getErrorsAsString()
            );
        }
    }

    public function isValid() {
        if ( $this->validator != null ) {
            $this->validator->flushErrors();
            $this->validator->validate();
            // TODO: change this
            $string = $this->validator->getErrors();
            $isEmpty = empty( $string );

            return  $isEmpty ;
        }
        return true;
    }

    /**
     * Try to set the validator for this struct automatically.
     */
    protected function tryValidator() {
        // try to set a validator for this struct it one exists
        $current_class = get_class( $this );

        // This regular expressions changes `FooStruct` in `FooValidator`
        $validator_name = preg_replace('/(.+)(Struct)$/', '\1Validator', $current_class );
        $validator_name = "\\$validator_name" ;

        try {
            $load = @class_exists($validator_name, true) ;
            if ( $load  ) {
                $this->validator = new $validator_name($this);
            }
        } catch ( \Exception $e ) {
            \Log::doJsonLog("Class not found $validator_name");
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
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public function toArray( $mask = null ){

        $attributes = array();
        $reflectionClass = new ReflectionObject( $this );
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

    /**
     * Compatibility with ArrayObject
     *
     * @return array
     * @throws ReflectionException
     */
    public function getArrayCopy(){
        return $this->toArray();
    }

    public function count() {
        $reflectionClass = new ReflectionObject( $this );
        return count( $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC ) );
    }


} 