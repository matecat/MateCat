<?php

namespace Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Exception;
use Klein\Request;
use Throwable;

abstract class Base {

    /**
     * @var Request
     */
    protected Request $request;

    protected KleinController $controller;

    /**
     * @var callable[]
     */
    protected array $_validationCallbacks = [];

    /**
     * @var callable
     */
    private $_failureCallback = null;

    public function __construct( KleinController $kleinController ) {
        $this->request    = $kleinController->getRequest();
        $this->controller = $kleinController;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected abstract function _validate(): void;

    /**
     * @throws Throwable
     */
    public function validate() {
        try {
            $this->_validate();
            $this->_executeCallbacks();
        } catch ( Throwable $exception ) {
            if ( !empty( $this->_failureCallback ) ) {
                ( $this->_failureCallback )( $exception ); // PHP7 closure call syntax: support operations on arbitrary (...) expressions
            } else {
                throw $exception;
            }
        }
    }

    /**
     * @param callable|null $callable
     */
    public function onSuccess( callable $callable = null ): Base {
        if ( is_callable( $callable ) ) {
            $this->_validationCallbacks[] = $callable;
        } else {
            trigger_error( "Invalid callback provided", E_USER_WARNING );
        }

        return $this;
    }

    public function onFailure( callable $callable = null ): Base {
        if ( is_callable( $callable ) ) {
            $this->_failureCallback = $callable;
        } else {
            trigger_error( "Invalid callback provided", E_USER_WARNING );
        }

        return $this;
    }

    /**
     * Execute Callbacks in pipeline
     * @throws Exception
     */
    protected function _executeCallbacks() {
        foreach ( $this->_validationCallbacks as $callable ) {
            $callable();
        }
    }

    /**
     * @return Request
     */
    public
    function getRequest(): Request {
        return $this->request;
    }

}
