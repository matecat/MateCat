<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use currency_translatedChangeRatesFetcher;

class FetchChangeRatesController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function fetch()
    {
        $changeRatesFetcher = new currency_translatedChangeRatesFetcher();
        $changeRatesFetcher->fetchChangeRates();

        $this->response->status()->setCode( 200 );
        $this->response->json( [
            'data' => $changeRatesFetcher->getChangeRates()
        ] );
    }
}