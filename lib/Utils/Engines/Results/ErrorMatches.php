<?php

class Engines_Results_ErrorMatches {

    public $code    = 0;
    public $message = "";

    public function __construct( $result = [] ) {
        if ( !empty( $result ) ) {
            $this->http_code = $result[ 'http_code' ] ?? null;
            $this->code      = $result[ 'code' ];
            $this->message   = $result[ 'message' ];
        }
    }

    public function get_as_array() {
        return (array)$this;
    }

}