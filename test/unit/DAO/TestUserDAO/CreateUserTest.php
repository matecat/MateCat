<?php

/**
 * @group  regression
 * @covers Users_UserDao::createUser
 * User: dinies
 * Date: 27/05/16
 * Time: 16.37
 */
class CreateUserTest extends AbstractTest {
    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    protected $user_struct_param;
    protected $sql_delete_user;
    protected $sql_select_user;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $uid;
    protected $actual;


    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->user_Dao          = new Users_UserDao( $this->database_instance );
        /**
         * user initialization
         */
        $this->user_struct_param                     = new Users_UserStruct();
        $this->user_struct_param->uid                = null;  //SET NULL FOR AUTOINCREMENT
        $this->user_struct_param->email              = "barandfoo@translated.net";
        $this->user_struct_param->create_date        = "2016-04-29 18:06:42";
        $this->user_struct_param->first_name         = "Edoardo";
        $this->user_struct_param->last_name          = "BarAndFoo";
        $this->user_struct_param->salt               = "801b32d6a9ce745";
        $this->user_struct_param->pass               = "bd40541bFAKE0cbar143033and731foo";
        $this->user_struct_param->oauth_access_token = "";
        /**
         * insertion
         */
        $this->actual = $this->user_Dao->createUser( $this->user_struct_param );
        $this->uid    = $this->actual->uid;
        /**
         * queries
         */
        $this->sql_select_user = "SELECT * FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid . "';";
        $this->sql_delete_user = "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid . "';";

    }


    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->select( INIT::$INSTANCE_ID );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers Users_UserDao::createUser
     */
    public function test_create_with_success() {

        $this->user_struct_param->uid = $this->uid;
        $this->assertEquals( $this->user_struct_param, $this->actual );

        $wrapped_result = $this->database_instance->getConnection()->query( $this->sql_select_user )->fetchAll( PDO::FETCH_ASSOC );
        $result         = $wrapped_result[ '0' ];
        $this->assertCount( 12, $result );
        $this->assertEquals( $this->uid, $result[ 'uid' ] );
        $this->assertEquals( "barandfoo@translated.net", $result[ 'email' ] );
        $this->assertEquals( "801b32d6a9ce745", $result[ 'salt' ] );
        $this->assertEquals( "bd40541bFAKE0cbar143033and731foo", $result[ 'pass' ] );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result[ 'create_date' ] );
        $this->assertEquals( "Edoardo", $result[ 'first_name' ] );
        $this->assertEquals( "BarAndFoo", $result[ 'last_name' ] );
        $this->assertNull( $result[ 'oauth_access_token' ] );
    }
}