<?php

namespace Validator\Contracts;

class ValidatorObject {

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var object
     */
    private $data;

    /**
     * @param array $errors
     */
    public function setErrors( $errors ) {
        foreach ($errors as $error){
            $this->addError($error);
        }
    }

    /**
     * @param $error
     */
    public function addError( $error ) {
        $this->errors[] = $error;
    }

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function isValid(){
        return empty($this->errors);
    }

    /**
     * @param $data
     */
    public function setData( $data ) {
        $this->data = $data;
    }

    /**
     * @return object
     */
    public function getData() {
        return $this->data;
    }
}
