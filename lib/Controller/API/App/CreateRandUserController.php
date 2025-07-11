<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Utils\Engines\EnginesFactory;

class CreateRandUserController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function create(): void {

        /**
         * @var $tms \Utils\Engines\MyMemory
         */
        $tms = EnginesFactory::getInstance( 1 );

        $this->response->json( [
                'data' => $tms->createMyMemoryKey()
        ] );

    }
}