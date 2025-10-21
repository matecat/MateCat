<?php

namespace Utils\Engines\Results\MyMemory;

use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 04/03/15
 * Time: 11.50
 */
class SetContributionResponse extends TMSAbstractResponse {

    public function __construct( $response ) {

        $this->responseData    = $response[ 'responseData' ] ?? '';
        $this->responseDetails = $response[ 'responseDetails' ] ?? '';
        $this->responseStatus  = (int)( $response[ 'responseStatus' ] ?? 200 );

    }

} 