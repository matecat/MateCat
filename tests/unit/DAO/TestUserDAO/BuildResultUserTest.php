<?php

use Model\DataAccess\Database;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers UserDao::_buildResult
 * User: dinies
 * Date: 27/05/16
 * Time: 18.50
 */
class BuildResultUserTest extends AbstractTest
{
    protected ReflectionMethod $method;
    protected UserDao $userDao;

    /**
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->userDao = new UserDao(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $reflector = new ReflectionClass($this->userDao);
        $this->method = $reflector->getMethod("_buildResult");
    }

    /**
     * This test builds an user object from an array that describes the properties
     * @group  regression
     * @covers UserDao::_buildResult
     * @throws ReflectionException
     */
    #[Test]
    public function test_build_result_from_simple_array()
    {
        $array_param = [
            0 =>
                [
                    'uid' => null,  //SET NULL FOR AUTOINCREMENT
                    'email' => "barandfoo@translated.net",
                    'create_date' => "2016-04-29 18:06:42",
                    'first_name' => "Edoardo",
                    'last_name' => "BarAndFoo",
                    'salt' => "801b32d6a9ce745",
                    'api_key' => "",
                    'pass' => "bd40541bFAKE0cbar143033and731foo",
                    'oauth_access_token' => ""
                ]
        ];

        $actual_array_of_user_structures = $this->method->invoke($this->userDao, $array_param);
        $actual_user_struct = $actual_array_of_user_structures['0'];
        $this->assertTrue($actual_user_struct instanceof UserStruct);

        $this->assertEquals("barandfoo@translated.net", $actual_user_struct->email);

        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $actual_user_struct->create_date);
        $this->assertEquals("Edoardo", $actual_user_struct->first_name);
        $this->assertEquals("BarAndFoo", $actual_user_struct->last_name);
        $this->assertNull($actual_user_struct->salt);
        $this->assertNull($actual_user_struct->pass);
        $this->assertNull($actual_user_struct->oauth_access_token);
    }
}