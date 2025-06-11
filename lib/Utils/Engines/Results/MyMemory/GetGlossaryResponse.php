<?php

class Engines_Results_MyMemory_GetGlossaryResponse extends Engines_Results_AbstractResponse {

    public $matches = [];

    /**
     * @throws Exception
     */
    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->matches = $response[ 'matches' ] ?? [];
    }

}