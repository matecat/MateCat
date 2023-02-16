<?php

class Engines_Results_MyMemory_CheckGlossaryResponse extends Engines_Results_AbstractResponse {

    public $matches = [];

    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->matches = isset( $response[ 'matches' ] ) ? $response[ 'matches' ] : [];
    }

}