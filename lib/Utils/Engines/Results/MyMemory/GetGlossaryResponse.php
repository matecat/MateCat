<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Utils\Engines\Results\TMSAbstractResponse;

class GetGlossaryResponse extends TMSAbstractResponse {

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