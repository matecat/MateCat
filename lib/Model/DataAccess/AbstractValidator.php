<?php

/**
 * Class DataAccess_AbstractValidator
 *
 * @deprecated Validation at Struct level is deprecated. Should be moved on top at Domain Model level.
 */

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

    protected $struct ;

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

    abstract function validate();

}
