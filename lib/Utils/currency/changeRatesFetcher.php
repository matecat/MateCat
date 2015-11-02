<?php
/**
 * Created by PhpStorm.
 * User: lorenzo
 * Date: 10/11/14
 * Time: 17.38
 */

abstract class currency_changeRatesFetcher {

    /**
     * A JSON containing all change rates (with reference to EUR)
     * @var String
     */
    protected $changeRates;


    public abstract function fetchChangeRates();


    public function getChangeRates() {
        return $this->changeRates;
    }

} 