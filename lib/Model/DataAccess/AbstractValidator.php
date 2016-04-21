<?php

abstract class DataAccess_AbstractValidator {
    /**
     * The errors array should be structured like this:
     *
     * $this->errors = array(
     *      array(
     *          'the property the error relates to',
     *          ' the actual error message'
     *          )
     *      );
     */
    protected $errors = array();

    public function __construct( $struct ) {
        $this->struct = $struct;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function flushErrors() {
        $this->errors = array();
    }

    public function getErrorMessages() {
        return array_map( function( $item ) {
            return implode(' ', $item);
        }, $this->errors );
    }

    public function getErrorsAsString() {
        return implode(', ', $this->getErrorMessages());
    }

    public function validate(){
        throw new \Exceptions\ValidationError( "Error: " . get_class( $this ) . "::validate() is not implemented. You must implement it before to perform a call." );
    }

}
