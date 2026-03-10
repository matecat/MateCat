<?php

use Model\DataAccess\Database;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers UserDao::getByUids
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
     * @var UserDao
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


    public function setUp(): void
    {
        parent::setUp();
        $this->database_instance = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $this->user_Dao = new UserDao($this->database_instance);

        /**
         * users insertion
         */
        $this->sql_insert_user_1 = "INSERT INTO " . AppConfig::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'bar_first@foo.net', '12345', '76554321qwerty', '2016-04-11 13:41:54', 'Bar_1', 'Foo_1');";
        $this->sql_insert_user_2 = "INSERT INTO " . AppConfig::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'bar_second@foo.net', '1543', '987654321qwerty', '2016-04-11 13:41:54', 'Bar_2', 'Foo_2');";
        $this->sql_insert_user_3 = "INSERT INTO " . AppConfig::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, 'bar_third@foo.net', '16785', '012354321qwerty', '2016-04-11 13:41:54', 'Bar_3', 'Foo_3');";
        /**
         * 1st
         */
        $this->database_instance->getConnection()->query($this->sql_insert_user_1);
        $this->uid_1 = $this->getTheLastInsertIdByQuery($this->database_instance);
        /**
         * 2nd
         */
        $this->database_instance->getConnection()->query($this->sql_insert_user_2);
        $this->uid_2 = $this->getTheLastInsertIdByQuery($this->database_instance);
        /**
         * 3rd
         */
        $this->database_instance->getConnection()->query($this->sql_insert_user_3);
        $this->uid_3 = $this->getTheLastInsertIdByQuery($this->database_instance);


        $this->sql_delete_user_1 = "DELETE FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid_1 . "';";
        $this->sql_delete_user_2 = "DELETE FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid_2 . "';";
        $this->sql_delete_user_3 = "DELETE FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid_3 . "';";
    }


    public function tearDown(): void
    {
        $this->database_instance->getConnection()->query($this->sql_delete_user_1);
        $this->database_instance->getConnection()->query($this->sql_delete_user_2);
        $this->database_instance->getConnection()->query($this->sql_delete_user_3);
        $this->flusher = new Predis\Client(AppConfig::$REDIS_SERVERS);
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers UserDao::getByUids
     */
    public function test_getByUids_with_success()
    {
        $array_param = [
            '0' => [
                'uid' => $this->uid_1
            ],
            '1' => [
                'uid' => $this->uid_2
            ],
            '2' => [
                'uid' => $this->uid_3
            ]
        ];
        $array_result = $this->user_Dao->getByUids($array_param);

        $this->assertCount(3, $array_result);
        $array_result = array_values($array_result);

        $user = $array_result['0'];
        $this->assertTrue($user instanceof UserStruct);
        $this->assertEquals("{$this->uid_1}", $user->uid);
        $this->assertEquals("bar_first@foo.net", $user->email);
        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date);
        $this->assertEquals("Bar_1", $user->first_name);
        $this->assertEquals("Foo_1", $user->last_name);
        $this->assertEquals(12345, $user->salt);
        $this->assertEquals('76554321qwerty', $user->pass);
        $this->assertNull($user->oauth_access_token);

        $user = $array_result['1'];
        $this->assertTrue($user instanceof UserStruct);
        $this->assertEquals("{$this->uid_2}", $user->uid);
        $this->assertEquals("bar_second@foo.net", $user->email);
        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date);
        $this->assertEquals("Bar_2", $user->first_name);
        $this->assertEquals("Foo_2", $user->last_name);
        $this->assertEquals(1543, $user->salt);
        $this->assertEquals('987654321qwerty', $user->pass);
        $this->assertNull($user->oauth_access_token);


        $user = $array_result['2'];
        $this->assertTrue($user instanceof UserStruct);
        $this->assertEquals("{$this->uid_3}", $user->uid);
        $this->assertEquals("bar_third@foo.net", $user->email);
        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date);
        $this->assertEquals("Bar_3", $user->first_name);
        $this->assertEquals("Foo_3", $user->last_name);
        $this->assertEquals(16785, $user->salt);
        $this->assertEquals('012354321qwerty', $user->pass);
        $this->assertNull($user->oauth_access_token);
    }


    /**
     * @group  regression
     * @covers UserDao::getByUids
     */
    public function test_getByUids_with_empty_param_for_code_coverage_purpose()
    {
        $this->assertEquals([], $this->user_Dao->getByUids([]));
    }


}