<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 12.33
 */
class Engines_Results_MyMemory_ExportResponse extends Engines_Results_AbstractResponse {

    public $id;
    public $resourceLink;

    public function __construct( $response ) {

        $this->responseDetails = isset( $response[ 'responseDetails' ] ) ? $response[ 'responseDetails' ] : '';
        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';
        $this->responseData    = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseData    = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->id              = isset( $response[ 'responseData' ][ 'id' ] ) ? $response[ 'responseData' ][ 'id' ] : '';
        $this->resourceLink    = isset( $response[ 'resourceLink' ] ) ? $response[ 'resourceLink' ] : '';
        $this->responseDetails = isset( $response[ 'status' ] ) ? $response[ 'status' ] : '';
        $this->estimatedTime   = isset( $response[ 'estimated_time' ] ) ? $response[ 'estimated_time' ] : '';

    }

} 