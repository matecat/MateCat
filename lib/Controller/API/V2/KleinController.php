<?php

namespace API\V2 ;

use Klein\Klein;

abstract class KleinController {

    /**
     * @var \Klein\Request
     */
    protected $request ;

    /**
     * @var \Klein\Response
     */
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
