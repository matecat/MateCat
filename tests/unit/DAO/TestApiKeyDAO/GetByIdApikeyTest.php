<?php

use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers ApiKeyDao::getById
 * User: dinies
 * Date: 16/06/16
 * Time: 19.14
 */
class GetByIdApikeyTest extends AbstractTest
{
    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var ApiKeyDao
     */
    protected $apikey_Dao;
    /**
     * @var ApiKeyStruct
     */
    protected $apikey_struct_param;
    protected $sql_delete_apikey;
    protected $sql_insert_apikey;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $apikey_id;


    public function setUp(): void
    {
        parent::setUp();
        $this->database_instance = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $this->apikey_Dao = new ApiKeyDao($this->database_instance);

        /**
         * apikey insertion
         */
        $this->sql_insert_apikey = "INSERT INTO " . AppConfig::$DB_DATABASE . ".`api_keys` " .
            " ( uid, api_key, api_secret, create_date, last_update, enabled ) " .
            " VALUES " .
            " ( '1999', 'c4ca4238bar92382fake509a6f758foo', 'api_secret' , '2016-06-16 18:06:29', '2016-06-16 19:06:30', '1') ";


        $this->database_instance->getConnection()->query($this->sql_insert_apikey);
        $this->apikey_id = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_apikey = "DELETE FROM " . AppConfig::$DB_DATABASE . ".`api_keys` WHERE uid='" . $this->apikey_id . "';";
    }


    public function tearDown(): void
    {
        $this->database_instance->getConnection()->query($this->sql_delete_apikey);
        $this->flusher = new Predis\Client(AppConfig::$REDIS_SERVERS);
        $this->flusher->flushdb();
        parent::tearDown();
    }

    public function test_getById()
    {
        $wrapped_result = $this->apikey_Dao->getById($this->apikey_id);
        $apikey = $wrapped_result['0'];
        $this->assertTrue($apikey instanceof ApiKeyStruct);
        $this->assertEquals("{$this->apikey_id}", $apikey->id);
        $this->assertEquals("1999", $apikey->uid);
        $this->assertEquals("c4ca4238bar92382fake509a6f758foo", $apikey->api_key);
        $this->assertEquals("api_secret", $apikey->api_secret);
        $this->assertEquals("2016-06-16 18:06:29", $apikey->create_date);
        $this->assertEquals("2016-06-16 19:06:30", $apikey->last_update);
        $this->assertEquals("1", $apikey->enabled);
    }
}