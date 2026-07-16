<?php


namespace Matecat\Core\Structs\TestApiKeyStruct;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\Factory\ApiKey;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

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
        $this->test_data = new stdClass();
        $this->test_data->api_key = ApiKey::create(['api_key' => self::TEST_API_KEY]);
    }

    public function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $conn = obtainTestDatabase()->getConnection();
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

    #[Test]
    public function test_validSecret_isCaseSensitive()
    {
        // hash_equals compares byte-exact, so the secret is case-sensitive even though
        // api_key is looked up under a case-insensitive DB collation.
        $struct = new ApiKeyStruct(['api_secret' => 'aB3xZq']);

        $this->assertTrue($struct->validSecret('aB3xZq'));
        $this->assertFalse($struct->validSecret('AB3XZQ'));
    }
}
