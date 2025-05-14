<?php

namespace API\Commons\Validators;

use AbstractControllers\KleinController;
use Exception;

class JSONRequestValidator extends Base {

    /**
     * @var KleinController
     */
    protected $controller;

    public function __construct( KleinController $controller ) {

        parent::__construct( $controller->getRequest() );
        $this->controller = $controller;

    }

    /**
     * @return mixed|void
     * @throws Exception
     */
    protected function _validate() {
        if ( !preg_match( '~^application/json~', $this->request->headers()->get( 'Content-Type' ) ) ) {
            throw new Exception('Content type provided not valid (application/json expected)', 405);
        }
    }
}
