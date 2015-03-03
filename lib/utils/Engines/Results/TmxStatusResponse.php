<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 15.33
 */

class Engines_Results_TmxStatusResponse extends Engines_Results_AbstractResponse{

    //response example: {"responseStatus":"202","responseData":{"id":495779}}

    public $id;

    public function __construct($response){
        $this->responseStatus = $response['responseStatus'];
        $this->responseData = $response['responseData'];
        $this->id = $response['id'];
    }

} 