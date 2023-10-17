<?php
/**
 * Created by PhpStorm.
 * User: lorenzo
 * Date: 10/11/14
 * Time: 17.52
 */

class currency_translatedCurrencyConverter extends currency_currencyConverter {


    public function computeNewAmount(){

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, "https://www.translated.net/hts/?f=changeRate&cid=htsdemo&p=htsdemo5&from=$this->currencyFrom&to=$this->currencyTo&amount=$this->amount" );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec ($ch);

        curl_close ($ch);

        $result = explode( "\n", $result );
        $this->newAmount = ( $result[ 0 ] == 1 ) ? $result[ 1 ] : 0;
    }

}
