<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */

abstract class DataAccess_AbstractDaoSilentStruct extends DataAccess_AbstractDaoObjectStruct {

    /**
     * @var DataAccess_AbstractValidator
     */
    protected $validator;

    protected $cached_results = array();

    public function __construct( Array $array_params = array() ) {
        parent::__construct( $array_params );
        $this->tryValidator();
    }

    public function __get( $name ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Trying to get an undefined property ' . $name );
        }

        return $this->$name;
    }

    public function __set( $name, $value ) {
        if ( property_exists( $this, $name ) ) {
            $this->$name = $value;
        }
    }

    public function setTimestamp($attribute, $timestamp) {
        $this->$attribute = date('c', $timestamp);
    }

    /**
     * @param $mask array
     * @return array
     * @see toArray
     */
    public function attributes( $mask = null ) {
        return $this->toArray( $mask );
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
            $load = class_exists($validator_name, true) ;
            if ( $load  ) {
                $this->validator = new $validator_name($this);
            }
        } catch ( \Exception $e ) {
            \Log::doLog("Class not found $validator_name");
        }
    }

}
