<?php


use TestHelpers\AbstractTest;

/**
 * @group  regression
 * @covers ApiKeys_ApiKeyStruct::validSecret
 * User: dinies
 * Date: 21/06/16
 * Time: 15.50
 */
class GetUserApiKeyTest extends AbstractTest {

    protected $uid;
    private   $test_data;

    function setup() {

        /**
         * environment initialization
         */
        $this->test_data          = new StdClass();
        $this->test_data->user    = Factory_User::create();
        $this->test_data->api_key = Factory_ApiKey::create( [
                'uid' => $this->test_data->user->uid,
        ] );
    }


    public function test_getUser_success() {
        $user = $this->test_data->api_key->getUser();
        $this->assertTrue( $user instanceof Users_UserStruct );
        $this->assertEquals( "{$this->test_data->user->uid}", $user->uid );
        $this->assertEquals( "{$this->test_data->user->email}", $user->email );
        $this->assertEquals( "{$this->test_data->user->salt}", $user->salt );
        $this->assertEquals( "{$this->test_data->user->pass}", $user->pass );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date );
        $this->assertEquals( "{$this->test_data->user->create_date}", $user->create_date );
        $this->assertEquals( "{$this->test_data->user->first_name}", $user->first_name );
        $this->assertEquals( "{$this->test_data->user->last_name}", $user->last_name );
    }

    public function test_getUser_failure() {
        $this->test_data->api_key->uid += 1000;
        $this->assertNull( $this->test_data->api_key->getUser() );

    }
}