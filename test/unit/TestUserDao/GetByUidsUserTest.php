<?php

/**
 * @group regression
 * @covers Users_UserDao::getByUids
 * User: dinies
 * Date: 27/05/16
 * Time: 20.23
 */
class GetByUidsUserTest extends AbstractTest
{


    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    protected $user_struct_param;
    protected $sql_delete_user_1;
    protected $sql_delete_user_2;
    protected $sql_delete_user_3;
    protected $sql_insert_user_1;
    protected $sql_insert_user_2;
    protected $sql_insert_user_3;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $uid_1;
    protected $uid_2;
    protected $uid_3;


    public function setUp()
    {
        parent::setUp();
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->user_Dao = new Users_UserDao($this->database_instance);

        /**
         * users insertion
         */
        $this->sql_insert_user_1 = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES (NULL, 'bar_first@foo.net', '12345trewq', '76554321qwerty', '2016-04-11 13:41:54', 'Bar_!', 'Foo_1', '');";
        $this->sql_insert_user_2 = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES (NULL, 'bar_second@foo.net', '1543trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar_2', 'Foo_2', '');";
        $this->sql_insert_user_3 = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name`, `api_key` ) VALUES (NULL, 'bar_third@foo.net', '16785trewq', '012354321qwerty', '2016-04-11 13:41:54', 'Bar_3', 'Foo_3', '');";
        /**
         * 1st
         */
        $this->database_instance->query($this->sql_insert_user_1);
        $this->uid_1 = $this->database_instance->getConnection()->lastInsertId();
        /**
         * 2nd
         */
        $this->database_instance->query($this->sql_insert_user_2);
        $this->uid_2 = $this->database_instance->getConnection()->lastInsertId();
        /**
         * 3rd
         */
        $this->database_instance->query($this->sql_insert_user_3);
        $this->uid_3 = $this->database_instance->getConnection()->lastInsertId();


        $this->sql_delete_user_1 = "DELETE FROM users WHERE uid='" . $this->uid_1 . "';";
        $this->sql_delete_user_2 = "DELETE FROM users WHERE uid='" . $this->uid_2 . "';";
        $this->sql_delete_user_3 = "DELETE FROM users WHERE uid='" . $this->uid_3 . "';";

    }


    public function tearDown()
    {

        $this->database_instance->query($this->sql_delete_user_1);
        $this->database_instance->query($this->sql_delete_user_2);
        $this->database_instance->query($this->sql_delete_user_3);
        $this->flusher = new Predis\Client(INIT::$REDIS_SERVERS);
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group regression
     * @covers Users_UserDao::getByUids
     */
    public function test_getByUids_with_success()
    {
        $array_param = array(
            '0' => array(
                'uid' => $this->uid_1
            ),
            '1' => array(
                'uid' => $this->uid_2
            ),
            '2' => array(
                'uid' => $this->uid_3
            )
        );
        $array_result = $this->user_Dao->getByUids($array_param);

        $this->assertCount(3, $array_result);


        $first_user = $array_result['0'];
        $this->assertTrue($first_user instanceof Users_UserStruct);
        $this->assertEquals("bar_first@foo.net", $first_user->email);

        $first_user = $array_result['1'];
        $this->assertTrue($first_user instanceof Users_UserStruct);
        $this->assertEquals("bar_second@foo.net", $first_user->email);

        $first_user = $array_result['2'];
        $this->assertTrue($first_user instanceof Users_UserStruct);
        $this->assertEquals("bar_third@foo.net", $first_user->email);

    }


    /**
     * @group regression
     * @covers Users_UserDao::getByUids
     */
    public function test_getByUids_with_empty_param()
    {
       $this->assertEquals(array(),$this->user_Dao->getByUids(array()));


    }


}