<?php

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Utils\Engines\Results\TMSAbstractResponse;

class KeysGlossaryResponse extends TMSAbstractResponse {

    public $entries = [];

    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->entries = $response[ 'entries' ] ?? [];
    }

    /**
     * @return bool
     */
    public function hasGlossary() {
        if ( empty( $this->entries ) ) {
            return false;
        }

        foreach ( $this->entries as $value ) {
            if ( $value === true ) {
                return true;
            }
        }

        return false;
    }

}