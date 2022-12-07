<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.17
 */
class Engines_Results_MyMemory_DomainsResponse extends Engines_Results_AbstractResponse {

    public $entries = [];

    public function __construct( $response ) {

        if ( !is_array( $response ) ) {
            throw new Exception( "Invalid Response", -1 );
        }

        $this->entries = isset( $response[ 'entries' ] ) ? $response[ 'entries' ] : [];
    }

}