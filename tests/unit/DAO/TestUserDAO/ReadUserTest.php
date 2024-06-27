<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Users_UserDao::read
 * User: dinies
 * Date: 27/05/16
 * Time: 20.09
 */
class ReadUserTest extends AbstractTest {


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
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'barandfoo@translated.net', '666777888', 'bd40541bFAKE0cbar143033and731foo', '2016-04-29 18:06:42', 'Edoardo', 'BarAndFoo');";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->uid = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_user = "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid . "';";

    }

    public function tearDown() {
        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers Users_UserDao::read
     */
    public function test_read_user_without_where_conditions() {
        $this->user_struct_param = Users_UserStruct::getStruct();
        $this->setExpectedException( 'Exception' );
        $this->user_Dao->setCacheTTL( 2 )->read( $this->user_struct_param );

    }

    /**
     * @group  regression
     * @covers Users_UserDao::read
     */
    public function test_read_user_with_success_uid_given() {
        $this->user_struct_param      = Users_UserStruct::getStruct();
        $this->user_struct_param->uid = $this->uid;
        $result_wrapped               = $this->user_Dao->setCacheTTL( 200 )->read( $this->user_struct_param );
        $result_wrapped               = $this->user_Dao->setCacheTTL( 200 )->read( $this->user_struct_param );
        $user                         = $result_wrapped[ '0' ];
        $this->assertTrue( $user instanceof Users_UserStruct );
        $this->assertEquals( $this->uid, $user->uid );
        $this->assertEquals( "barandfoo@translated.net", $user->email );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date );
        $this->assertEquals( "Edoardo", $user->first_name );
        $this->assertEquals( "BarAndFoo", $user->last_name );
        $this->assertNull( $user->salt );
        $this->assertNull( $user->pass );
        $this->assertNull( $user->oauth_access_token );
    }

    /**
     * @group  regression
     * @covers Users_UserDao::read
     */
    public function test_read_user_with_success_email_given() {
        $this->user_struct_param        = Users_UserStruct::getStruct();
        $this->user_struct_param->email = "barandfoo@translated.net";
        $result_wrapped                 = $this->user_Dao->setCacheTTL( 2 )->read( $this->user_struct_param );
        $user                           = $result_wrapped[ '0' ];
        $this->assertTrue( $user instanceof Users_UserStruct );
        $this->assertEquals( $this->uid, $user->uid );
        $this->assertEquals( "barandfoo@translated.net", $user->email );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date );
        $this->assertEquals( "Edoardo", $user->first_name );
        $this->assertEquals( "BarAndFoo", $user->last_name );
        $this->assertNull( $user->salt );
        $this->assertNull( $user->pass );
        $this->assertNull( $user->oauth_access_token );
    }
}