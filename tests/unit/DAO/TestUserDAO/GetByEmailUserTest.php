<?php

use Model\DataAccess\Database;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers UserDao::getByEmail
 * User: dinies
 * Date: 27/05/16
 * Time: 17.46
 */
class GetByEmailUserTest extends AbstractTest {


    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var UserDao
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
    protected $email;


    public function setUp(): void {
        parent::setUp();
        $this->database_instance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );
        $this->user_Dao          = new UserDao( $this->database_instance );

        /**
         * user insertion
         */
        $this->email           = "bar@foo.net";
        $this->sql_insert_user = "INSERT INTO " . AppConfig::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, '" . $this->email . "', '12345', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo');";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->uid = $this->getTheLastInsertIdByQuery( $this->database_instance );

        $this->sql_delete_user = "DELETE FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid . "';";

    }


    public function tearDown(): void {

        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->flusher = new Predis\Client( AppConfig::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    public function test_getByEmail() {
        $user = $this->user_Dao->getByEmail( $this->email );

        $this->assertTrue( $user instanceof UserStruct );
        $this->assertEquals( "{$this->uid}", $user->uid );
        $this->assertEquals( "bar@foo.net", $user->email );
        $this->assertEquals( "12345", $user->salt );
        $this->assertEquals( "987654321qwerty", $user->pass );
        $this->assertEquals( "2016-04-11 13:41:54", $user->create_date );
        $this->assertEquals( "Bar", $user->first_name );
        $this->assertEquals( "Foo", $user->last_name );

    }
}