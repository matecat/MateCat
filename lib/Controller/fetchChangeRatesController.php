<?php

class fetchChangeRatesController extends ajaxController
{
    /**
     * Class constructor, simply call parent constructor
     *
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Perform Controller Action
     *
     * @return json|null
     */
    public function doAction() {
        $changeRatesFetcher = new currency_translatedChangeRatesFetcher();
        $changeRatesFetcher->fetchChangeRates();

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = $changeRatesFetcher->getChangeRates();
    }

}