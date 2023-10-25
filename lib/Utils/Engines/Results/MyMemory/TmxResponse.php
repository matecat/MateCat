<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 15.33
 */

class Engines_Results_MyMemory_TmxResponse extends Engines_Results_AbstractResponse {

    /*
    {
        "messageType": "tms-import",
        "responseData": {
            "uuid": "eab692c7-0872-aa4f-5abf-9cd333df48f0",
            "id": null,
            "creation_date": "2023-04-28 15:32:24",
            "totals": null,
            "completed": 0,
            "skipped": 0,
            "status": 0
        },
        "responseStatus": 202
    }
    */

    public $id;

    public function __construct( $response ) {

        $this->responseData    = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';
        $this->responseDetails = isset( $response[ 'responseDetails' ] ) ? $response[ 'responseDetails' ] : '';

        if ( $this->responseStatus == 200 || $this->responseStatus == 202 ) {
            $this->id = empty( $this->responseData[ 'uuid' ] ) ? $this->responseData[ 'UUID' ] : $this->responseData[ 'uuid' ];
        } else {
            Log::doJsonLog( $response );
        }
    }

} 