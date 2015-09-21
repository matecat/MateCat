<?php

class Factory_ApiKey extends Factory_Base {

    static function create( $values ) {

        $values = array_merge(array(
            'uid' => 1,
            'api_key' => '1234abcd',
            'api_secret' => 'secretcode',
            'enabled' => true
        ), $values );

        $dao = new ApiKeys_ApiKeyDao( Database::obtain() );
        $struct = new ApiKeys_ApiKeyStruct( $values );

        return $dao->create( $struct );


    }
}
