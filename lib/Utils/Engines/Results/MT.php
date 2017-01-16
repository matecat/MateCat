<?php


class Engines_Results_MT {

    public $translatedText = "";
    public $sentence_confidence;
    public $error = "";

    public function __construct( $result ) {
        $this->error = new Engines_Results_ErrorMatches();
        if ( is_array( $result ) and array_key_exists( "data", $result ) ) {
            $this->translatedText = $result[ 'data' ][ 'translations' ][ 0 ][ 'translatedText' ];
            if ( isset( $result[ 'data' ][ 'translations' ][ 0 ][ 'sentence_confidence' ] ) ) {
                $this->sentence_confidence = $result[ 'data' ][ 'translations' ][ 0 ][ 'sentence_confidence' ];
            }
        }

        if ( is_array( $result ) and array_key_exists( "error", $result ) ) {
            $this->error = new Engines_Results_ErrorMatches( $result[ 'error' ] );
        }
    }

    public function get_as_array() {
        if( $this->error != "" ) $this->error = $this->error->get_as_array();
        return (array)$this;
    }

}