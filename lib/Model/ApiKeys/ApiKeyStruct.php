<?php

class ApiKeys_ApiKeyStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $uid ;
    public $api_key  ;
    public $api_secret  ;
    public $create_date  ;
    public $last_update  ;
    public $enabled  ;

}
