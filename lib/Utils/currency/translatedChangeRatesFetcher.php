<?php
/**
 * Created by PhpStorm.
 * User: lorenzo
 * Date: 10/11/14
 * Time: 17.52
 */

class currency_translatedChangeRatesFetcher extends currency_changeRatesFetcher {

    /**
     * curl the service and store change rates in an instance variable
     */
    public function fetchChangeRates() {

        $ch = curl_init();

        curl_setopt( $ch, CURLOPT_URL, "www.translated.net/hts/matecat-endpoint.php?f=getChangeRates&cid=htsdemo&p=htsdemo5&of=json" );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 5000 );

        $output = json_decode( curl_exec( $ch ), true );

        curl_close( $ch );

        // SAMPLE OUTPUT
        //  {
        //      "code": 1,
        //      "EUR": "1",
        //      "USD": "1.1085",
        //      "JPY": "133.38",
        //      "BGN": "1.9558",
        //      ..... etc .....
        //  }
        // if everything went fine (code=1), unset the "code" key before returning to the client
        if ( $output[ "code" ] == 1 ) {
            unset( $output[ "code" ] );
            $this->changeRates = json_encode( $output );
        }

    }

} 