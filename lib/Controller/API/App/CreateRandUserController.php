<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use Engine;
use Engines_MyMemory;
use Exception;
use Klein\Response;

class CreateRandUserController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function create(): Response
    {
        try {
            /**
             * @var $tms Engines_MyMemory
             */
            $tms = Engine::getInstance( 1 );

            return $this->response->json([
                'data' => $tms->createMyMemoryKey()
            ]);
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }
}