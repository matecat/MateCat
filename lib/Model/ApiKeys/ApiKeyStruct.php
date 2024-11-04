<?php

class ApiKeys_ApiKeyStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    public ?int    $id = null;
    public int    $uid;
    public string $api_key;
    public string $api_secret;
    public string $create_date;
    public string $last_update;
    public bool   $enabled;

    public function validSecret( $secret ): bool {
        return $this->api_secret == $secret;
    }

    /**
     * @throws ReflectionException
     */
    public function getUser() {
        $dao = new Users_UserDao( Database::obtain() );
        $dao->setCacheTTL( 3600 );

        return $dao->getByUid( $this->uid );
    }
}
