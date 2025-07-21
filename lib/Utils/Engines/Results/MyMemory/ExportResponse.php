<?php

namespace Utils\Engines\Results\MyMemory;

use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 03/03/15
 * Time: 12.33
 */
class ExportResponse extends TMSAbstractResponse {

    public $id;
    public $resourceLink;
    /**
     * @var mixed|string
     */
    public $estimatedTime;

    public function __construct( $response ) {

        $this->responseStatus  = $response[ 'responseStatus' ] ?? '';
        $this->responseData    = $response[ 'responseData' ] ?? '';
        $this->id              = $response[ 'responseData' ][ 'id' ] ?? '';
        $this->resourceLink    = $response[ 'resourceLink' ] ?? '';
        $this->responseDetails = $response[ 'status' ] ?? '';
        $this->estimatedTime   = $response[ 'estimated_time' ] ?? '';

    }

} 