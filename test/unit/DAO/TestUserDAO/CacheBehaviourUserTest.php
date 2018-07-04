<?php

/**
 * @group regression
 * @covers Users_UserDao
 * User: dinies
 * Date: 31/05/16
 * Time: 11.37
 */
class CacheBehaviourUserTest extends AbstractTest
{

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

    public function setUp()
    {
        parent::setUp();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->user_Dao = new Users_UserDao($this->database_instance);

        /**
         * user insertion
         */

        $this->user_struct_param = new Users_UserStruct();
        $this->user_struct_param->uid = NULL;  //SET NULL FOR AUTOINCREMENT
        $this->user_struct_param->email = "barandfoo@translated.net";
        $this->user_struct_param->create_date = "2016-04-29 18:06:42";
        $this->user_struct_param->first_name = "Edoardo";
        $this->user_struct_param->last_name = "BarAndFoo";
        $this->user_struct_param->salt = "801b32d6a9ce745";
        $this->user_struct_param->api_key = "";
        $this->user_struct_param->pass = "bd40541bFAKE0cbar143033and731foo";
        $this->user_struct_param->oauth_access_token = "";
        
        
        $this->user_Dao->createUser($this->user_struct_param);
        $this->uid=$this->database_instance->getConnection()->lastInsertId();

        $this->cache = new Predis\Client(INIT::$REDIS_SERVERS);

        $this->sql_delete_user = "DELETE FROM ".INIT::$DB_DATABASE.".`users` WHERE uid='" . $this->uid . "';";

    }
    public function tearDown()
    {
        $this->database_instance->query($this->sql_delete_user);
        $this->cache->flushdb();
        parent::tearDown();
    }
    
    
    /**
     * @group regression
     * @covers Users_UserDao::read
     */
    public function test_read_user_with_cache_hit(){
        


        $UserQuery=Users_UserStruct::getStruct();
        $UserQuery->uid=$this->uid;

        $reflector = new ReflectionClass($this->user_Dao);
        $method = $reflector->getMethod("_getStatementForCache");
        $method->setAccessible(true);

        $const= $reflector->getConstant('TABLE');

        /**
         * Code Duplication
         * Taken from  @see Users_UserDao::read
         * Begin
         */


        $UserQuery = $this->user_Dao->sanitize( $UserQuery );

        $where_conditions = array();
        $where_parameters = array();

        $query            = "SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM " . $const . " WHERE %s";

        if ( $UserQuery->uid !== null ) {
            $where_conditions[] = "uid = :uid";
            $where_parameters[ 'uid' ] = $UserQuery->uid;
        }

        if ( $UserQuery->email !== null ) {
            $where_conditions[] = "email = :email";
            $where_parameters[ 'email' ] = $UserQuery->email;
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );
        /**
         * end
         */
        $stmt = $method->invoke($this->user_Dao, $query );


        /**
         * Cache miss
         */

        $cache_query= $stmt->queryString . serialize($where_parameters);

        $cache_result= unserialize( $this->cache->get( md5($cache_query)));

        $this->assertFalse($cache_result);

        /**
         * Cache insertion
         */

        $result_wrapped=$this->user_Dao->setCacheTTL(20)->read($UserQuery);
        $read_result= $result_wrapped['0'];

        $this->assertTrue( $read_result instanceof Users_UserStruct);
        $this->assertEquals($this->uid, $read_result->uid);
        $this->assertEquals("barandfoo@translated.net", $read_result->email);



        /**
         * Cache hit
         */

        $result_wrapped= unserialize( $this->cache->get( md5($cache_query)));
        $cache_result= $result_wrapped['0'];
        $this->assertTrue( $cache_result instanceof Users_UserStruct);
        $this->assertEquals($this->uid, $cache_result->uid);
        $this->assertEquals("barandfoo@translated.net", $cache_result->email);


        $this->assertEquals($read_result,$cache_result);

    }
}