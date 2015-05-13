<?php
/**
 * Created by PhpStorm.
 * User: lorenzo
 * Date: 10/11/14
 * Time: 17.38
 */

abstract class currency_currencyConverter {

    /**
     * The starting currency
     * @var float
     */
    protected $amount;

    /**
     * The starting currency
     * @var string
     */
    protected $currencyFrom;

    /**
     * The starting currency
     * @var string
     */
    protected $currencyTo;

    /**
     * The starting currency
     * @var float
     */
    protected $newAmount;



    public function setAmount( $amount ) {
        $this->amount = $amount;
    }


    public function setCurrencyFrom( $currencyFrom ) {
        $this->currencyFrom = $currencyFrom;
    }


    public function setCurrencyTo( $currencyTo ) {
        $this->currencyTo = $currencyTo;
    }


    public abstract function computeNewAmount();


    public function getNewAmount() {
        return $this->newAmount;
    }

} 