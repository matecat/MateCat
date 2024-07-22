<?php

use Predis\Client;
use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers DataAccess_AbstractDao::_setInCache
 * User: dinies
 * Date: 18/04/16
 * Time: 15.05
 */
class SetInCacheTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    /**
     * @var Client
     */
    protected $cache_con;
    protected $cache_TTL;
    protected $cache_key;
    protected $cache_value_for_the_key;

    public function setUp()
    {
        parent::setUp();
        $this->databaseInstance = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->method           = $this->reflector->getMethod("_setInCache");
        $this->method->setAccessible(true);
        $this->cache_con = $this->reflector->getProperty("cache_con");
        $this->cache_con->setAccessible(true);

        $this->cache_con->setValue($this->databaseInstance, new Client(INIT::$REDIS_SERVERS));

        $this->cache_TTL= $this->reflector->getProperty("cacheTTL");
        $this->cache_TTL->setAccessible(true);
        $this->cache_TTL->setValue($this->databaseInstance, 30);

    }
    public function tearDown()
    {
        $this->cache_con->getValue($this->databaseInstance)-> flushdb();
        parent::tearDown();
    }

    /**
     * It set in cache a (key => value) record and it checks that will be available for the get from cache.
     * @group regression
     * @covers DataAccess_AbstractDao::_setInCache
     */
    public function test__setInCache_basic_key_value(){
        $this->cache_key = "key";
        $this->cache_value_for_the_key = "foo_bar";
        $this->method->invoke($this->databaseInstance , $this->cache_key, $this->cache_value_for_the_key);
        $this->assertEquals($this->cache_value_for_the_key, unserialize(   $this->cache_con->getValue($this->databaseInstance) ->get(md5($this->cache_key))));
    }
    /**
     * It set in cache a (key => value) record and it checks that will be available for the get from cache.
     * @group regression
     * @covers DataAccess_AbstractDao::_setInCache
     */
    public function test__setInCache_frequent_key_value(){
        $this->cache_key = "SELECT * FROM engines WHERE id = 0 AND active = 0 AND type = 'NONE'";

        $this->cache_value_for_the_key = array(0 =>
            array(
                "id" => "0",
                "name" => "NONE",
                "type" => "NONE",
                "description" => "No MT",
                "base_url" => "",
                "translate_relative_url" => "",
                "contribute_relative_url" => NULL,
                "delete_relative_url" => NULL,
                "others" => "{}",
                "class_load" => "NONE",
                "extra_parameters" => "",
                "google_api_compliant_version" => NULL,
                "penalty" => "100",
                "active" => "0",
                "uid" => NULL
            ));

        $this->method->invoke($this->databaseInstance , $this->cache_key, $this->cache_value_for_the_key);
        $this->assertEquals($this->cache_value_for_the_key, unserialize(   $this->cache_con->getValue($this->databaseInstance) ->get(md5($this->cache_key))));
    }
    /**
     * It set in cache a (key => value) record and it checks that will be available for the get from cache.
     * @group regression
     * @covers DataAccess_AbstractDao::_setInCache
     */
    public function test__setInCache_user()
    {


        /**
         * Params
         */
        $database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $user_Dao = new Users_UserDao($database_instance);

        $this->reflector = new ReflectionClass($user_Dao);
        $method_getStatementForCache = $this->reflector->getMethod("_getStatementForCache");
        $method_getStatementForCache->setAccessible(true);


        $stmt_param= $method_getStatementForCache->invoke($user_Dao,"SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM users WHERE uid = :uid");


        $uid = 999;

        $bindParams_param= array(
            'uid' => $uid
        );



        $user_struct = new Users_UserStruct();
        $user_struct->uid = $uid;
        $user_struct->email = "barandfoo@translated.net";
        $user_struct->create_date = "2016-04-29 18:06:42";
        $user_struct->first_name = "Edoardo";
        $user_struct->last_name = "BarAndFoo";
        $user_struct->salt = NULL;
        $user_struct->api_key = NULL;
        $user_struct->pass = NULL;
        $user_struct->oauth_access_token = NULL;
        $user_struct->validator = NULL;

        $this->cache_value_for_the_key = array(
            '0' => $user_struct
        );



        $this->cache_key=  $stmt_param->queryString . serialize( $bindParams_param ) ;



        $this->method->invoke($this->databaseInstance , $this->cache_key, $this->cache_value_for_the_key);
        $this->assertEquals($this->cache_value_for_the_key, unserialize(   $this->cache_con->getValue($this->databaseInstance) ->get(md5($this->cache_key))));
    
        
    }
    

}