<?php

use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * @group  regression
 * @covers ApiKeyStruct::validSecret
 */
class ValidSecretTest extends AbstractTest
{
    private const TEST_API_KEY = 'test_valid_secret_key';

    private $test_data;

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
        $this->test_data = new StdClass();
        $this->test_data->api_key = Factory_ApiKey::create(['api_key' => self::TEST_API_KEY]);
    }

    public function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("DELETE FROM api_keys WHERE api_key = :key");
        $stmt->execute(['key' => self::TEST_API_KEY]);
    }

    #[Test]
    public function test_validSecret_success()
    {
        $this->assertTrue($this->test_data->api_key->validSecret($this->test_data->api_key->api_secret));
    }

    #[Test]
    public function test_validSecret_failure()
    {
        $this->assertFalse($this->test_data->api_key->validSecret($this->test_data->api_key->api_secret . "made_invalid"));
    }
}
