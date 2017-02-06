<?php

class ApiKeys_ApiKeyStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    public $id;
    public $uid;
    public $api_key;
    public $api_secret;
    public $create_date;
    public $last_update;
    public $enabled;

    public function validSecret( $secret ) {
        return $this->api_secret == $secret;
    }

    public function getUser() {
        $dao = new Users_UserDao( Database::obtain() );
        $dao->setCacheTTL( 3600 );
        $user = $dao->getByUid( $this->uid );
        return $user;
    }
}
