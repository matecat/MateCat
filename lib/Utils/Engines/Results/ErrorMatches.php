<?php

class Engines_Results_ErrorMatches {

    public $code = 0;
    public $message = "";

    public function __construct( $result = array() ) {
        if ( !empty( $result ) ) {
            $this->http_code = $result[ 'message' ];
            $this->code      = $result[ 'code' ];
            $this->message   = $result[ 'message' ];
        }
    }

    public function get_as_array() {
        return (array)$this;
    }

}