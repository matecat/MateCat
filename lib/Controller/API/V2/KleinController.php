<?php

namespace API\V2 ;

abstract class KleinController {

    protected $request ;
    protected $response ;
    protected $service ;
    protected $app ;

    public function __construct( $request, $response, $service, $app) {
        $this->request = $request ;
        $this->response = $response ;
        $this->service = $service ;
        $this->app = $app ;

        $this->afterConstruct();
    }

    public function respond($method) {
        $this->$method() ;
    }
}
