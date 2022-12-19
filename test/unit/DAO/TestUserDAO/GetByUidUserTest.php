<?php

/**
 * @group  regression
 * @covers Users_UserDao::getByUid
 * User: dinies
 * Date: 27/05/16
 * Time: 17.21
 */
class GetByUidUserTest extends AbstractTest {
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
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'bar@foo.net', '12345', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo' );";
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

    public function test_getByUid() {
        $user = $this->user_Dao->getByUid( $this->uid );

        $this->assertTrue( $user instanceof Users_UserStruct );
        $this->assertEquals( "{$this->uid}", $user->uid );
        $this->assertEquals( "bar@foo.net", $user->email );
        $this->assertEquals( "12345", $user->salt );
        $this->assertEquals( "987654321qwerty", $user->pass );
        $this->assertEquals( "2016-04-11 13:41:54", $user->create_date );
        $this->assertEquals( "Bar", $user->first_name );
        $this->assertEquals( "Foo", $user->last_name );
    }

}