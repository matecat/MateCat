<?php

class Engines_Results_MyMemory_KeysGlossaryResponse extends Engines_Results_AbstractResponse {

    public $entries = [];

    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->entries = isset( $response[ 'entries' ] ) ? $response[ 'entries' ] : [];
    }

    /**
     * @return bool
     */
    public function hasGlossary()
    {
        if(empty($this->entries)){
            return false;
        }

        foreach($this->entries as $key => $value){
            if($value === true){
                return true;
            }
        }

        return false;
    }

}