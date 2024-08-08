<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers DataAccess_AbstractDao::_getFromCache
 * User: dinies
 * Date: 07/06/16
 * Time: 15.49
 */
class AbstractGetFromCacheUserTest extends AbstractTest {
    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    protected $reflector;
    protected $method_getFromCache;
    protected $cache_con;
    protected $cache_TTL;
    protected $cache_key;
    protected $cache_value;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $sql_insert_user;
    protected $sql_delete_user;
    protected $uid;
    protected $user_struct_param;
    protected $method_getStatementForCache;
    protected $stmt_param;
    protected $bindParams_param;

    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->user_Dao          = new Users_UserDao( $this->database_instance );
        $this->reflector         = new ReflectionClass( $this->user_Dao );

        $this->method_getFromCache = $this->reflector->getMethod( "_getFromCache" );
        $this->method_getFromCache->setAccessible( true );

        $this->reflector                   = new ReflectionClass( Users_UserDao::class );
        $this->method_getStatementForCache = $this->reflector->getMethod( "_getStatementForCache" );
        $this->method_getStatementForCache->setAccessible( true );


        $this->cache_con = $this->reflector->getProperty( "cache_con" );
        $this->cache_con->setAccessible( true );
        $this->cache_con->setValue( $this->user_Dao, ( new RedisHandler() )->getConnection() );

        $this->cache_TTL = $this->reflector->getProperty( "cacheTTL" );
        $this->cache_TTL->setAccessible( true );
        $this->cache_TTL->setValue( $this->user_Dao, 30 );

        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'barandfoo@translated.net', '666777888', 'bd40541bFAKE0cbar143033and731foo', '2016-04-29 18:06:42', 'Edoardo', 'BarAndFoo');";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->uid = $this->database_instance->last_insert();

        $this->user_struct_param      = new Users_UserStruct();
        $this->user_struct_param->uid = $this->uid;

        $this->sql_delete_user = "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid . "';";


        /**
         * Initialization of match in cache for the cache hit
         */
        $user_struct                     = new Users_UserStruct();
        $user_struct->uid                = $this->uid;
        $user_struct->email              = "barandfoo@translated.net";
        $user_struct->create_date        = "2016-04-29 18:06:42";
        $user_struct->first_name         = "Edoardo";
        $user_struct->last_name          = "BarAndFoo";
        $user_struct->salt               = null;
        $user_struct->pass               = null;
        $user_struct->oauth_access_token = null;

        $this->cache_value = [
                '0' => $user_struct
        ];


        /**
         * Params
         */
        $this->stmt_param = new PDOStatement();

        $this->stmt_param = $this->method_getStatementForCache->invoke( $this->user_Dao, "SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM users WHERE uid = :uid" );

        $this->bindParams_param = [
                'uid' => $this->uid
        ];

        $this->cache_key = $this->stmt_param->queryString . serialize( $this->bindParams_param );


    }

    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->cache_con->getValue( $this->user_Dao )->flushdb();
        parent::tearDown();
    }


    /**
     * It gets from the cache a common engine tied to a frequent key.
     * @group  regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_simple_engine_with_artificial_insertion_in_cache() {

        $this->cache_con->getValue( $this->user_Dao )->setex( md5( $this->cache_key ), $this->cache_TTL->getValue( $this->user_Dao ), serialize( $this->cache_value ) );
        $expected_return = $this->method_getFromCache->invoke( $this->user_Dao, $this->cache_key );
        $this->assertEquals( $this->cache_value, $expected_return );
    }

    /**
     * It gets from the cache a common engine tied to a frequent key.
     * @group  regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_engine_just_created() {

        $this->user_Dao->setCacheTTL( 10 );
        $this->user_Dao->read( $this->user_struct_param );
        $expected_return = $this->method_getFromCache->invoke( $this->user_Dao, $this->cache_key );
        $this->assertEquals( $this->cache_value, $expected_return );
    }

}