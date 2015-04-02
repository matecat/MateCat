<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 04/03/15
 * Time: 11.50
 */

class Engines_Results_MyMemory_SetContributionResponse extends Engines_Results_AbstractResponse{

    public function __construct($response){
        $this->responseStatus  = $response['responseStatus'];
        $this->responseData    = $response['responseData'];
        $this->responseDetails = $response['responseDetails'];
    }

} 