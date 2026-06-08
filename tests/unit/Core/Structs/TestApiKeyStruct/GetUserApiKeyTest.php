<?php


namespace Matecat\Core\Structs\TestApiKeyStruct;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\Factory\ApiKey;
use Matecat\TestHelpers\Factory\User;
use Model\DataAccess\Database;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

/**
 * @group  regression
 * @covers ApiKeyStruct::getUser
 */
class GetUserApiKeyTest extends AbstractTest
{
    private const TEST_API_KEY = 'test_get_user_api_key';

    protected $uid;
    private $test_data;

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
        $this->test_data = new stdClass();
        $this->test_data->user = User::create();
        $this->test_data->api_key = ApiKey::create([
            'uid' => $this->test_data->user->uid,
            'api_key' => self::TEST_API_KEY,
        ]);
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
    public function test_getUser_success()
    {
        $user = $this->test_data->api_key->getUser();
        $this->assertTrue($user instanceof UserStruct);
        $this->assertEquals("{$this->test_data->user->uid}", $user->uid);
        $this->assertEquals("{$this->test_data->user->email}", $user->email);
        $this->assertEquals("{$this->test_data->user->salt}", $user->salt);
        $this->assertEquals("{$this->test_data->user->pass}", $user->pass);
        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date);
        $this->assertEquals("{$this->test_data->user->create_date}", $user->create_date);
        $this->assertEquals("{$this->test_data->user->first_name}", $user->first_name);
        $this->assertEquals("{$this->test_data->user->last_name}", $user->last_name);
    }

    #[Test]
    public function test_getUser_failure()
    {
        $this->test_data->api_key->uid += 1000;
        $this->assertNull($this->test_data->api_key->getUser());
    }
}
