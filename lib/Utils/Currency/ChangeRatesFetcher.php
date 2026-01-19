<?php

namespace Utils\Currency;

/**
 * Created by PhpStorm.
 * User: lorenzo
 * Date: 10/11/14
 * Time: 17.38
 */
abstract class ChangeRatesFetcher
{

    /**
     * A JSON containing all change rates (with reference to EUR)
     * @var String
     */
    protected string $changeRates;


    public abstract function fetchChangeRates();


    public function getChangeRates(): string
    {
        return $this->changeRates;
    }

} 