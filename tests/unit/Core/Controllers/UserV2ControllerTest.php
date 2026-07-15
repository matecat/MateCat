<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\UserController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Users\MetadataStruct;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use TypeError;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see UserController} (API/V2).
 *
 * Reserved ID block: base = 9_030_000 (task N=30).
 *   base+6 = uid (9_030_006). Per-suite owner email: ctrltest_9030000@example.org.
 * Clean ONLY by reserved id (ControllerSeedFragments::cleanFragments).
 */
class TestableUserV2Controller extends UserController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class UserV2ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_030_000;

    /** @var ReflectionClass<UserController> */
    private ReflectionClass $reflector;
    private TestableUserV2Controller $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws \Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedUser(self::BASE, $this->ownerEmail(self::BASE));

        $this->controller = new TestableUserV2Controller();
        $this->reflector = new ReflectionClass(UserController::class);

        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('response', $this->responseMock);
        $this->setProp('request', new Request());
        $this->setProp('database', obtainTestDatabase());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $user->create_date = '2020-01-01 00:00:00';
        $this->setProp('user', $user);

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ReflectionException
     */
    private function setBody(array $payload): void
    {
        $body = (string) json_encode($payload);
        $serverParams = ['REQUEST_URI' => '/api/v2/user', 'REQUEST_METHOD' => 'POST'];
        $request = new Request([], [], [], $serverParams, [], $body);
        $this->setProp('request', $request);
    }

    // ─── edit() ───

    /**
     * @throws ReflectionException
     * @throws TypeError
     * @throws PHPUnitException
     */
    #[Test]
    public function edit_returns_updated_profile_payload_on_success(): void
    {
        $this->setBody(['first_name' => 'Mario', 'last_name' => 'Rossi']);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame($this->userId(self::BASE), $data['uid']);
                $this->assertSame('Mario', $data['first_name']);
                $this->assertSame('Rossi', $data['last_name']);
                $this->assertSame($this->ownerEmail(self::BASE), $data['email']);
                $this->assertArrayHasKey('create_date', $data);
                return true;
            }));

        $this->controller->edit();
    }

    /**
     * @throws ReflectionException
     * @throws TypeError
     * @throws PHPUnitException
     */
    #[Test]
    public function edit_returns_error_400_when_first_name_missing(): void
    {
        $this->setBody(['last_name' => 'Rossi']);

        $this->responseMock->expects($this->once())->method('code')->with(400);
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('First name must contain at least one letter', $data['error']);
                return true;
            }));

        $this->controller->edit();
    }

    /**
     * @throws ReflectionException
     * @throws TypeError
     * @throws PHPUnitException
     */
    #[Test]
    public function edit_returns_error_400_when_last_name_missing(): void
    {
        $this->setBody(['first_name' => 'Mario']);

        $this->responseMock->expects($this->once())->method('code')->with(400);
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame('Last name must contain at least one letter', $data['error']);
                return true;
            }));

        $this->controller->edit();
    }

    // ─── setMetadata() ───

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws PHPUnitException
     */
    #[Test]
    public function setMetadata_returns_metadata_struct_payload_on_success(): void
    {
        $this->setBody(['key' => 'layout', 'value' => 'horizontal']);

        $captured = [];
        $this->responseMock->method('json')
            ->willReturnCallback(function (mixed $data) use (&$captured) {
                $captured[] = $data;
                return $this->responseMock;
            });

        $this->controller->setMetadata();

        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM user_metadata WHERE uid = " . $this->userId(self::BASE));

        $this->assertCount(1, $captured, 'setMetadata must emit exactly one json payload');
        $payload = $captured[0];
        $this->assertInstanceOf(MetadataStruct::class, $payload);
        $this->assertSame((string) $this->userId(self::BASE), (string) $payload->uid);
        $this->assertSame('layout', $payload->key);
        $this->assertSame('horizontal', $payload->value);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws PHPUnitException
     */
    #[Test]
    public function setMetadata_returns_metadata_struct_payload_on_success_with_array_value(): void
    {
        $this->setBody(['key' => 'preferences', 'value' => ['theme' => 'dark', 'notifications' => 'enabled']]);

        $captured = [];
        $this->responseMock->method('json')
            ->willReturnCallback(function (mixed $data) use (&$captured) {
                $captured[] = $data;
                return $this->responseMock;
            });

        $this->controller->setMetadata();

        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM user_metadata WHERE uid = " . $this->userId(self::BASE));

        $this->assertCount(1, $captured, 'setMetadata must emit exactly one json payload');
        $payload = $captured[0];
        $this->assertInstanceOf(MetadataStruct::class, $payload);
        $this->assertSame((string) $this->userId(self::BASE), (string) $payload->uid);
        $this->assertSame('preferences', $payload->key);
        $this->assertIsArray($payload->value);
        $this->assertSame('dark', $payload->value['theme']);
        $this->assertSame('enabled', $payload->value['notifications']);
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    #[Test]
    public function setMetadata_throws_when_key_missing(): void
    {
        $this->setBody(['value' => 'horizontal']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setMetadata();
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    #[Test]
    public function setMetadata_throws_when_value_missing(): void
    {
        $this->setBody(['key' => 'layout']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setMetadata();
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    #[Test]
    public function setMetadata_throws_403_when_user_not_authenticated(): void
    {
        $user = new UserStruct();
        $user->uid = null;
        $this->setProp('user', $user);

        $this->setBody(['key' => 'layout', 'value' => 'horizontal']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(403);

        $this->controller->setMetadata();
    }
}
