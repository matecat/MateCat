<?php

namespace API\V2\Validators;

use API\V2\KleinController;
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
        if($this->request->headers()->get('Content-Type') !== 'application/json'){
            throw new Exception('Content type provided not valid (application/json expected)', 405);
        }
    }
}
