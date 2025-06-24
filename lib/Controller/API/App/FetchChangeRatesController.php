<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use currency_translatedChangeRatesFetcher;

class FetchChangeRatesController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function fetch(): void {

        $changeRatesFetcher = new currency_translatedChangeRatesFetcher();
        $changeRatesFetcher->fetchChangeRates();

        $this->response->json( [
                "errors" => [],
                "code"   => 1,
                'data'   => $changeRatesFetcher->getChangeRates()
        ] );

    }
}