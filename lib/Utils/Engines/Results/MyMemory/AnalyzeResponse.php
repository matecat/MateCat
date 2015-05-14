<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 04/03/15
 * Time: 11.50
 */

class Engines_Results_MyMemory_AnalyzeResponse extends Engines_Results_AbstractResponse{

    public function __construct($response){

        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';
        $this->responseDetails = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseData    = isset( $response[ 'data' ] ) ? $response[ 'data' ] : '';

    }

} 