<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Utils\Currency\ChangeRatesFetcher;
use Utils\Currency\TranslatedChangeRatesFetcher;

class FetchChangeRatesController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    protected function getChangeRatesFetcher(): ChangeRatesFetcher
    {
        return new TranslatedChangeRatesFetcher();
    }

    /**
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function fetch(): void
    {
        $changeRatesFetcher = $this->getChangeRatesFetcher();
        $changeRatesFetcher->fetchChangeRates();

        $this->response->json([
            "errors" => [],
            "code" => 1,
            'data' => $changeRatesFetcher->getChangeRates()
        ]);
    }
}