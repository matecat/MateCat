<?php

namespace API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Engine;
use Engines_MyMemory;
use Exception;

class CreateRandUserController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function create(): void {

        /**
         * @var $tms Engines_MyMemory
         */
        $tms = Engine::getInstance( 1 );

        $this->response->json( [
                'data' => $tms->createMyMemoryKey()
        ] );

    }
}