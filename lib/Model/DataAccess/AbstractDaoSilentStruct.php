<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */

abstract class DataAccess_AbstractDaoSilentStruct extends DataAccess_AbstractDaoObjectStruct {
    protected $validator;
    protected $cached_results = array();

    public function __construct( Array $array_params = array() ) {
        parent::__construct( $array_params );
        $this->tryValidator();
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
     */
    protected function cachable($method_name, $params, $function) {
      if ( !key_exists($method_name,  $this->cached_results) ) {
        $this->cached_results[$method_name] =
          call_user_func($function, $params);
      }
      return $this->cached_results[$method_name];
    }

    public function __get( $name ) {
        if (!property_exists( $this, $name )) {
            throw new DomainException( 'Trying to get an undefined property ' . $name );
        }
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            // TODO: write to logs once we'll be able to have
            // distinct log levels. Should go in DEBUG level.
            // Log::doLog("DEBUG: Unknown property $name");
        }
    }

    /**
     *
     * @deprecation use `attributes` method instead
     */
    public function toArray(){
        Log::doLog('DEPRECATED, use `attributes()` method instead');
        return $this->attributes();
    }

    /**
     * Returns an array of the public attributes of the struct.
     * If $keys_to_return is provided, the resulting array will include
     * only the specified keys.
     *
     * This method is useful in conjunction with PDO execute, where only
     * a subset of the attributes may be required to be bound to the query.
     *
     * @return Array
     */
    public function attributes( $keys_to_return=null ) {
        $refclass = new ReflectionClass( $this );
        $attrs = array();
        $publicProperties = $refclass->getProperties(ReflectionProperty::IS_PUBLIC) ;
        foreach( $publicProperties as $property ) {
            if ( !empty($keys_to_return) ) {
                if (! in_array( $property->getName(), $keys_to_return)) {
                    continue;
                }
            }
            $attrs[$property->getName()] = $property->getValue($this);
        }
        return $attrs;
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
    private function tryValidator() {
        // try to set a validator for this struct it one exists
        $current_class = get_class( $this );

        // This regular expressions changes `FooStruct` in `FooValidator`
        $validator_name = preg_replace('/(.+)(Struct)$/', '\1Validator', $current_class );
        $validator_name = "\\$validator_name" ;

        try {
            $load = class_exists($validator_name, true) ;
            if ( $load  ) {
                $this->validator = new $validator_name($this);
            }
        } catch ( \Exception $e ) {
            \Log::doLog("Exception class not found $validator_name");
        }
    }

}
