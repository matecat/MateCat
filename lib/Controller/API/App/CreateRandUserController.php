<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;

class CreateRandUserController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        $tms = $this->getEngine();

        $this->response->json([
            'data' => $tms->createMyMemoryKey()
        ]);
    }

    /**
     * @throws Exception
     */
    protected function getEngine(): MyMemory
    {
        return EnginesFactory::getInstance(1, $this->getDatabase(), MyMemory::class);
    }
}