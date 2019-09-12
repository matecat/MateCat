<?php

namespace API\V2\Validators;

use Exception;
use Klein\Request;

abstract class Base {

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var callable[]
     */
    protected $_validationCallbacks = [];

    public function __construct( Request $kleinRequest ) {
        $this->request = $kleinRequest ;
    }

    /**
     * @throws Exception
     * @return mixed
     */
    protected abstract function _validate();

    /**
     * @throws Exception
     */
    public function validate(){
        $this->_validate();
        $this->_executeCallbacks();
    }

    /**
     * @param callable|null $callable
     */
    public function onSuccess( callable $callable = null ){
        if ( !is_callable( $callable ) ) return;
        $this->_validationCallbacks[] = $callable;
    }

    /**
     * Execute Callbacks in pipeline
     * @throws Exception
     */
    protected function _executeCallbacks(){
        foreach( $this->_validationCallbacks as $callable ){
            $callable();
        }
    }

    /**
     * @return Request
     */
    public function getRequest() {
        return $this->request;
    }

}
