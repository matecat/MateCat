<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_fetchObject
 * User: dinies
 * Date: 31/05/16
 * Time: 17.54
 */
class AbstractFetchObjectUserTest extends AbstractTest
{

    /**
     * @var \Predis\Client
     */
    protected $cache;
    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    /**
     * @var Users_UserStruct
     */
    protected $fetchClass_param;
    protected $user_struct_param;

    protected $sql_insert_user;
    protected $sql_delete_user;

    /**
     * @var Database
     */
    protected $database_instance;
    protected $uid;

    protected $reflector;
    protected $method_fetchObject;
    protected $method_getStatementForCache;
    protected $method_setInCache;
    protected $stmt_param;
    protected $bindParams_param;


    public function setUp()
    {
        parent::setUp();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->user_Dao = new Users_UserDao($this->database_instance);
        
        /**
         * user insertion
         */
        $this->sql_insert_user = "INSERT INTO ".INIT::$DB_DATABASE.".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES (NULL, 'barandfoo@translated.net', '666777888', 'bd40541bFAKE0cbar143033and731foo', '2016-04-29 18:06:42', 'Edoardo', 'BarAndFoo', '');";
        $this->database_instance->query($this->sql_insert_user);
        $this->uid= $this->database_instance->last_insert();

        $this->sql_delete_user = "DELETE FROM users WHERE uid='" . $this->uid . "';";


        $this->reflector = new ReflectionClass($this->user_Dao);
        $this->method_fetchObject = $this->reflector->getMethod("_fetchObject");
        $this->method_fetchObject->setAccessible(true);


        $this->reflector = new ReflectionClass($this->user_Dao);
        $this->method_setInCache = $this->reflector->getMethod("_setInCache");
        $this->method_setInCache->setAccessible(true);


        $this->reflector = new ReflectionClass($this->user_Dao);
        $this->method_getStatementForCache = $this->reflector->getMethod("_getStatementForCache");
        $this->method_getStatementForCache->setAccessible(true);
        /**
         * Params
         */

        $this->stmt_param= $this->method_getStatementForCache->invoke($this->user_Dao,"SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM users WHERE uid = :uid");

        $this->fetchClass_param=Users_UserStruct::getStruct();
        $this->fetchClass_param->uid=$this->uid;

        $this->bindParams_param= array(
            'uid' => (int)$this->uid
        );

    }
    public function tearDown()
    {
        $this->cache = new Predis\Client(INIT::$REDIS_SERVERS);

        $this->database_instance->query($this->sql_delete_user);
        $this->cache->flushdb();
        parent::tearDown();
    }
    /**
     * @group regression
     * @covers DataAccess_AbstractDao::_fetchObject
     */
    public function test__fetchObject_with_cache_hit(){

        /**
         * Initialization of match in cache for the cache hit
         */
        $user_struct = new Users_UserStruct();
        $user_struct->uid = $this->uid;
        $user_struct->email = "barandfoo@translated.net";
        $user_struct->create_date = "2016-04-29 18:06:42";
        $user_struct->first_name = "Edoardo";
        $user_struct->last_name = "BarAndFoo";
        $user_struct->salt = NULL;
        $user_struct->api_key = NULL;
        $user_struct->pass = NULL;
        $user_struct->oauth_access_token = NULL;
        $user_struct->validator = NULL;

        $result_param=array(
            '0' => $user_struct
        );

        $cache_key=  $this->stmt_param->queryString . serialize( $this->bindParams_param ) ;


        $cache_TTL= (new ReflectionClass($this->user_Dao))->getProperty("cacheTTL");
        $cache_TTL->setAccessible(true);
        $cache_TTL->setValue($this->user_Dao, 30);


        $method_setCacheConnection= (new ReflectionClass($this->user_Dao))->getMethod('_cacheSetConnection');
        $method_setCacheConnection->setAccessible(true);
        $method_setCacheConnection->invoke($this->user_Dao);
        
        
        $this->method_setInCache->invoke($this->user_Dao, $cache_key , $result_param);
        /**
         * Cached
         * Mock Object Creation
         */
        $mock_user_Dao = $this->getMockBuilder('\Users_UserDao')
            ->setConstructorArgs(array($this->database_instance))
            ->setMethods(array('_setInCache'))
            ->getMock();

        $mock_user_Dao->expects($this->never())
            ->method('_setInCache');

        $mock_user_Dao->setCacheTTL(20);

        /**
         * _fetchObject invocation
         */
        $wrapped_result= $this->method_fetchObject->invoke($mock_user_Dao,$this->stmt_param,$this->fetchClass_param,$this->bindParams_param);

        $result= $wrapped_result['0'];

        $this->assertEquals($this->uid,$result->uid);
        $this->assertEquals("barandfoo@translated.net",$result->email);
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->create_date);
        $this->assertEquals("Edoardo",$result->first_name);
        $this->assertEquals("BarAndFoo",$result->last_name);
        $this->assertNull($result->salt);
        $this->assertNull($result->api_key);
        $this->assertNull($result->pass);
        $this->assertNull($result->oauth_access_token);
        $this->assertNull($result->validator);

    }

    /**
     * @group regression
     * @covers DataAccess_AbstractDao::_fetchObject
     */
    public function test__fetchObject_with_cache_miss(){

        /**
         * Not cached
         * Mock Object Creation
         */
        $mock_user_Dao = $this->getMockBuilder('\Users_UserDao')
            ->setConstructorArgs(array($this->database_instance))
            ->setMethods(array('_setInCache'))
            ->getMock();

        $mock_user_Dao->expects($this->exactly(1))
            ->method('_setInCache');

        $mock_user_Dao->setCacheTTL(20);

        /**
         * _fetchObject invocation
         */
        $wrapped_result= $this->method_fetchObject->invoke($mock_user_Dao,$this->stmt_param,$this->fetchClass_param,$this->bindParams_param);

        $result= $wrapped_result['0'];

        $this->assertEquals($this->uid,$result->uid);
        $this->assertEquals("barandfoo@translated.net",$result->email);
        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->create_date);
        $this->assertEquals("Edoardo",$result->first_name);
        $this->assertEquals("BarAndFoo",$result->last_name);
        $this->assertNull($result->salt);
        $this->assertNull($result->api_key);
        $this->assertNull($result->pass);
        $this->assertNull($result->oauth_access_token);
        $this->assertNull($result->validator);

    }


}