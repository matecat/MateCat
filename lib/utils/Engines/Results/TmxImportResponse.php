<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 15.33
 */
class Engines_Results_TmxImportResponse extends Engines_Results_AbstractResponse {

    public $id;

    public function __construct( $response ) {
        $this->responseStatus = $response[ 'responseStatus' ];
        $this->responseData   = $response[ 'responseData' ];
        if ( $this->responseStatus == 200 ) {
            $this->id = $response[ 'id' ];
        }
    }

}