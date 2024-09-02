<?php


use TestHelpers\AbstractTest;

/**
 * @group  regression
 * @covers ApiKeys_ApiKeyStruct::validSecret
 * User: dinies
 * Date: 21/06/16
 * Time: 15.39
 */
class ValidSecretTest extends AbstractTest {

    private $test_data;

    public function setUp(): void {

        $this->test_data          = new StdClass();
        $this->test_data->api_key = Factory_ApiKey::create( [] );
    }


    public function test_validSecret_success() {
        $this->assertTrue( $this->test_data->api_key->validSecret( $this->test_data->api_key->api_secret ) );

    }

    public function test_validSecret_failure() {
        $this->assertFalse( $this->test_data->api_key->validSecret( $this->test_data->api_key->api_secret . "made_invalid" ) );

    }
}