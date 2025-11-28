<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Utils\Currency\TranslatedChangeRatesFetcher;

class FetchChangeRatesController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    public function fetch(): void
    {
        $changeRatesFetcher = new TranslatedChangeRatesFetcher();
        $changeRatesFetcher->fetchChangeRates();

        $this->response->json([
                "errors" => [],
                "code"   => 1,
                'data'   => $changeRatesFetcher->getChangeRates()
        ]);
    }
}