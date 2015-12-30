<?php

namespace API\V2\Validators;

abstract class Base {

    protected $request;

    public function __construct( $request ) {
        $this->request = $request ;
    }

}
