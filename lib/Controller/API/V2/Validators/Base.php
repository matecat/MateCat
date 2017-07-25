<?php

namespace API\V2\Validators;

use Klein\Request;

abstract class Base {

    /**
     * @var Request
     */
    protected $request;

    public function __construct( $request ) {
        $this->request = $request ;
    }

    abstract function validate();

}
