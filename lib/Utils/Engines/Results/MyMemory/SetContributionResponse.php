<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 04/03/15
 * Time: 11.50
 */

class Engines_Results_MyMemory_SetContributionResponse extends Engines_Results_AbstractResponse{

    public function __construct( $response ){

        $this->responseData    = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseDetails = isset( $response[ 'responseDetails' ] ) ? $response[ 'responseDetails' ] : '';
        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';

    }

} 