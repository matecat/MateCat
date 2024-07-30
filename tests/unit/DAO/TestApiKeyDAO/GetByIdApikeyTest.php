<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers ApiKeys_ApiKeyDao::getById
 * User: dinies
 * Date: 16/06/16
 * Time: 19.14
 */
class GetByIdApikeyTest extends AbstractTest {
    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var ApiKeys_ApiKeyDao
     */
    protected $apikey_Dao;
    /**
     * @var ApiKeys_ApiKeyStruct
     */
    protected $apikey_struct_param;
    protected $sql_delete_apikey;
    protected $sql_insert_apikey;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $apikey_id;


    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->apikey_Dao        = new ApiKeys_ApiKeyDao( $this->database_instance );

        /**
         * apikey insertion
         */
        $this->sql_insert_apikey = "INSERT INTO " . INIT::$DB_DATABASE . ".`api_keys` " .
                " ( uid, api_key, api_secret, create_date, last_update, enabled ) " .
                " VALUES " .
                " ( '1999', 'c4ca4238bar92382fake509a6f758foo', 'api_secret' , '2016-06-16 18:06:29', '2016-06-16 19:06:30', '1') ";


        $this->database_instance->getConnection()->query( $this->sql_insert_apikey );
        $this->apikey_id = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_apikey = "DELETE FROM " . INIT::$DB_DATABASE . ".`api_keys` WHERE uid='" . $this->apikey_id . "';";

    }


    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_apikey );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    public function test_getById() {
        $wrapped_result = $this->apikey_Dao->getById( $this->apikey_id );
        $apikey         = $wrapped_result[ '0' ];
        $this->assertTrue( $apikey instanceof ApiKeys_ApiKeyStruct );
        $this->assertEquals( "{$this->apikey_id}", $apikey->id );
        $this->assertEquals( "1999", $apikey->uid );
        $this->assertEquals( "c4ca4238bar92382fake509a6f758foo", $apikey->api_key );
        $this->assertEquals( "api_secret", $apikey->api_secret );
        $this->assertEquals( "2016-06-16 18:06:29", $apikey->create_date );
        $this->assertEquals( "2016-06-16 19:06:30", $apikey->last_update );
        $this->assertEquals( "1", $apikey->enabled );

    }
}