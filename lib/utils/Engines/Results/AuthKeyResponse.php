<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 18.24
 */

class Engines_Results_AuthKeyResponse extends Engines_Results_AbstractResponse {

    public function __construct( $result ){
        $this->responseData = $result;
        $this->responseStatus = 200;
    }

}