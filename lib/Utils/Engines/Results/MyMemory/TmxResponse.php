<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 15.33
 */

class Engines_Results_MyMemory_TmxResponse extends Engines_Results_AbstractResponse{

    //response example: {"responseStatus":"202","responseData":{"id":495779}}

    public $id;

    public function __construct( $response ){

        $this->responseData    = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';
        $this->responseDetails = isset( $response[ 'responseDetails' ] ) ? $response[ 'responseDetails' ] : '';

        if ( $this->responseStatus == 200 || $this->responseStatus == 202 ) {

            if( !isset( $this->responseData[ 'tm' ] ) ){
                //TMX IMPORT STATUS CARRIES A LIST and not a single element, skip the id assignment
                $this->id = $this->responseData[ 'id' ];
            }

        }
        else {
            Log::doJsonLog($response);
        }
    }

} 