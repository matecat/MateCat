<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\EngineOwnershipValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Lara\LaraException;
use Utils\Engines\Lara;

class LaraController extends KleinController
{

    protected Lara $laraClient;

    protected function afterConstruct(): void
    {
        parent::afterConstruct();

        $loginValidator       = new LoginValidator($this);
        $engineOwnerValidator = new EngineOwnershipValidator($this, filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT), Lara::class);

        $loginValidator->onSuccess(function () use ($engineOwnerValidator) {
            $engineOwnerValidator->validate();
        })->onSuccess(function () use ($engineOwnerValidator) {
            $this->laraClient = $engineOwnerValidator->getEngine();
        });

        $this->appendValidator($loginValidator);
    }

    /**
     * Get all the customer's Lara glossaries
     * @throws LaraException
     */
    public function glossaries(): void
    {
        $glossaries = $this->laraClient->getGlossaries();

        $this->response->status()->setCode(200);
        $this->response->json($glossaries);
    }

}