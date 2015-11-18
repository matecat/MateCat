<?php

class Factory_ApiKey extends Factory_Base {

    private static $unique_key = 0;

    static function create( $values ) {
        self::$unique_key = self::$unique_key + 1 ;

        $values = array_merge(array(
            'uid' => 1,
            'api_key' => md5(self::$unique_key),
            'api_secret' => 'api_secret',
            'enabled' => true
        ), $values );

        $dao = new ApiKeys_ApiKeyDao( Database::obtain() );
        $struct = new ApiKeys_ApiKeyStruct( $values );

        return $dao->create( $struct );


    }
}
