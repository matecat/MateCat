<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Users_UserDao
 * User: dinies
 * Date: 31/05/16
 * Time: 11.37
 */
class CacheBehaviourUserTest extends AbstractTest {

    /**
     * @var \Predis\Client
     */
    protected $cache;
    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    protected $user_struct_param;
    protected $sql_delete_user;
    protected $sql_insert_user;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $uid;

    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->user_Dao          = new Users_UserDao( $this->database_instance );

        /**
         * user insertion
         */

        $this->user_struct_param                     = new Users_UserStruct();
        $this->user_struct_param->uid                = null;  //SET NULL FOR AUTOINCREMENT
        $this->user_struct_param->email              = "barandfoo@translated.net";
        $this->user_struct_param->create_date        = "2016-04-29 18:06:42";
        $this->user_struct_param->first_name         = "Edoardo";
        $this->user_struct_param->last_name          = "BarAndFoo";
        $this->user_struct_param->salt               = "801b32d6a9ce745";
        $this->user_struct_param->api_key            = "";
        $this->user_struct_param->pass               = "bd40541bFAKE0cbar143033and731foo";
        $this->user_struct_param->oauth_access_token = "";


        $record    = $this->user_Dao->createUser( $this->user_struct_param );
        $this->uid = $record->uid;

        $this->cache = ( new RedisHandler() )->getConnection();
        $this->cache->select( (int)INIT::$INSTANCE_ID );

        $this->sql_delete_user = "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid . "';";

    }

    public function tearDown() {
        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->cache->flushdb();
        parent::tearDown();
    }


    /**
     * @group  regression
     * @covers Users_UserDao::read
     */
    public function test_read_user_with_cache_hit() {


        $UserQuery      = Users_UserStruct::getStruct();
        $UserQuery->uid = $this->uid;

        $reflector = new ReflectionClass( $this->user_Dao );
        $readQuery = $reflector->getMethod( '_buildReadQuery' );
        $readQuery->setAccessible( true );
        [ $query, $bind_params ] = $readQuery->invoke( $this->user_Dao, $UserQuery );

        $getStatementMethod = $reflector->getMethod( "_getStatementForCache" );
        $getStatementMethod->setAccessible( true );
        $stmt = $getStatementMethod->invoke( $this->user_Dao, $query );

        $serializeMethod = $reflector->getMethod( '_serializeForCacheKey' );
        $serializeMethod->setAccessible( true );
        $serializedParams = $serializeMethod->invoke( $this->user_Dao, $bind_params );

        /**
         * Cache miss
         */
        $cache_query = md5( $stmt->queryString . $serializedParams );

        $cache_result = unserialize( $this->cache->get( $cache_query ) );

        $this->assertFalse( $cache_result );

        /**
         * Cache insertion
         */
        $result_wrapped = $this->user_Dao->setCacheTTL( 20 )->read( $UserQuery );
        $read_result    = $result_wrapped[ '0' ];

        $this->assertTrue( $read_result instanceof Users_UserStruct );
        $this->assertEquals( $this->uid, $read_result->uid );
        $this->assertEquals( "barandfoo@translated.net", $read_result->email );


        /**
         * Cache hit
         */
        $result_wrapped = unserialize( $this->cache->get( $cache_query ) );
        $cache_result   = $result_wrapped[ '0' ];
        $this->assertTrue( $cache_result instanceof Users_UserStruct );
        $this->assertEquals( $this->uid, $cache_result->uid );
        $this->assertEquals( "barandfoo@translated.net", $cache_result->email );


        $this->assertEquals( $read_result, $cache_result );

    }
}