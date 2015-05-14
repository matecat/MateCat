<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 18.24
 */

class Engines_Results_MyMemory_AuthKeyResponse extends Engines_Results_AbstractResponse {

    public function __construct( $response ){

        $this->responseData    = isset( $response ) ? $response : '';
        $this->responseStatus  = 200;

    }

}