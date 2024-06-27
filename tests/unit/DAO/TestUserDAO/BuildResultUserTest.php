<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers Users_UserDao::_buildResult
 * User: dinies
 * Date: 27/05/16
 * Time: 18.50
 */
class BuildResultUserTest extends AbstractTest
{
    protected $array_param;
    protected $reflector;
    protected $method;


    public function setUp()
    {
        parent::setUp();
        $this->databaseInstance = new Users_UserDao(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE));
        $this->reflector        = new ReflectionClass($this->databaseInstance);
        $this->method           = $this->reflector->getMethod("_buildResult");
        $this->method->setAccessible(true);


    }

    /**
     * This test builds an user object from an array that describes the properties
     * @group regression
     * @covers Users_UserDao::_buildResult
     */
    public function test_build_result_from_simple_array()
    {

        $this->array_param = array(0 =>
            array(
                'uid' => NULL,  //SET NULL FOR AUTOINCREMENT
                'email' => "barandfoo@translated.net",
                'create_date' => "2016-04-29 18:06:42",
                'first_name' => "Edoardo",
                'last_name' => "BarAndFoo",
                'salt' => "801b32d6a9ce745",
                'api_key' => "",
                'pass' => "bd40541bFAKE0cbar143033and731foo",
                'oauth_access_token' => ""
            ));

        $actual_array_of_user_structures = $this->method->invoke($this->databaseInstance, $this->array_param);
        $actual_user_struct = $actual_array_of_user_structures['0'];
        $this->assertTrue($actual_user_struct instanceof Users_UserStruct);

        $this->assertEquals("barandfoo@translated.net", $actual_user_struct->email);

        $this->assertRegExp('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $actual_user_struct->create_date);
        $this->assertEquals("Edoardo", $actual_user_struct->first_name);
        $this->assertEquals("BarAndFoo", $actual_user_struct->last_name);
        $this->assertNull( $actual_user_struct->salt);
        $this->assertNull( $actual_user_struct->pass);
        $this->assertNull( $actual_user_struct->oauth_access_token);
    }
}