<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use currency_translatedChangeRatesFetcher;
use Exception;
use Klein\Response;

class FetchChangeRatesController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function fetch(): Response
    {
        try {
            $changeRatesFetcher = new currency_translatedChangeRatesFetcher();
            $changeRatesFetcher->fetchChangeRates();

            return $this->response->json( [
                "errors" => [],
                "code" => 1,
                'data' => $changeRatesFetcher->getChangeRates()
            ] );
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }
}