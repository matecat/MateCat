<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Utils\Engines\Results\TMSAbstractResponse;

class UpdateGlossaryResponse extends TMSAbstractResponse {

    //public $entries = [];

    /**
     * @throws Exception
     */
    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->responseData    = $response[ 'responseData' ] ?? '';
        $this->responseDetails = $response[ 'responseDetails' ] ?? '';
        $this->responseStatus  = $response[ 'responseStatus' ] ?? '';
    }

}