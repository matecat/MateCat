<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers ApiKeys_ApiKeyDao::create
 * User: dinies
 * Date: 16/06/16
 * Time: 18.57
 */
class CreateApyKeyTest extends AbstractTest {
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
    protected $sql_select_apikey;
    protected $apikey_id;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $actual_apikey;

    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

        $this->apikey_Dao          = new ApiKeys_ApiKeyDao( $this->database_instance );
        $this->apikey_struct_param = new ApiKeys_ApiKeyStruct();


        $this->apikey_struct_param->uid         = '1999';
        $this->apikey_struct_param->api_key     = 'c4ca4238bar92382fake509a6f758foo';
        $this->apikey_struct_param->api_secret  = 'api_secret';
        $this->apikey_struct_param->create_date = '2016-06-16 18:06:29';
        $this->apikey_struct_param->last_update = '2016-06-16 19:06:30';
        $this->apikey_struct_param->enabled     = '1';

        $this->database_instance->getConnection()->query( "DELETE FROM `api_keys` WHERE 1" );

    }


    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_apikey );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers ApiKeys_ApiKeyDao::create
     */
    public function test_create_with_success() {

        $this->actual_apikey     = $this->apikey_Dao->create( $this->apikey_struct_param );
        $this->apikey_id         = $this->actual_apikey->id;
        $this->sql_select_apikey = "SELECT * FROM " . INIT::$DB_DATABASE . ".`api_keys` WHERE id='" . $this->apikey_id . "';";
        $this->sql_delete_apikey = "DELETE FROM " . INIT::$DB_DATABASE . ".`api_keys` WHERE id='" . $this->apikey_id . "';";

        $this->apikey_struct_param->id = $this->apikey_id;
        $this->assertEquals( $this->apikey_struct_param, $this->actual_apikey );

        $wrapped_result = $this->database_instance->getConnection()->query( $this->sql_select_apikey )->fetchAll( PDO::FETCH_ASSOC );
        $result         = $wrapped_result[ '0' ];
        $this->assertCount( 7, $result );
        $this->assertEquals( $this->apikey_id, $result[ 'id' ] );
        $this->assertEquals( "1999", $result[ 'uid' ] );
        $this->assertEquals( "c4ca4238bar92382fake509a6f758foo", $result[ 'api_key' ] );
        $this->assertEquals( "api_secret", $result[ 'api_secret' ] );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result[ 'create_date' ] );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result[ 'last_update' ] );
        $this->assertEquals( '1', $result[ 'enabled' ] );


    }
}