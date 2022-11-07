<?php

class Engines_Results_MyMemory_SetGlossaryResponse extends Engines_Results_AbstractResponse {

    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->responseData    = isset( $response[ 'responseData' ] ) ? $response[ 'responseData' ] : '';
        $this->responseDetails = isset( $response[ 'responseDetails' ] ) ? $response[ 'responseDetails' ] : '';
        $this->responseStatus  = isset( $response[ 'responseStatus' ] ) ? $response[ 'responseStatus' ] : '';
    }

}