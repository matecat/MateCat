<?php

namespace Utils\Engines\Results\MyMemory;

use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 04/03/15
 * Time: 11.50
 */
class AnalyzeResponse extends TMSAbstractResponse {

    public function __construct( $response ) {

        $this->responseStatus  = (int)( $response[ 'responseStatus' ] ?? 200 );
        $this->responseDetails = $response[ 'responseData' ] ?? '';
        $this->responseData    = $response[ 'data' ] ?? '';

    }

} 