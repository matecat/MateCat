<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\ApiKeyController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Filter\IsAnInternalUserEvent;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see ApiKeyController} (controller-coverage plan N=17).
 *
 * Reserved ID block base = 9_017_000 (9_000_000 + 17 * 1000):
 *   the api_keys table is keyed only by `uid` (no project/job FK), so this
 *   suite reserves uid = base + 6 = 9017006 and cleans ONLY that uid.
 * Per-suite owner email: ctrltest_9017000@example.org (never the shared one).
 */
class TestableApiKeyController extends ApiKeyController
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

/**
 * FeatureSet stub that marks the dispatched IsAnInternalUserEvent as internal,
 * so InternalUserValidator passes without loading real plugin features.
 */
class InternalFeatureSet extends FeatureSet
{
    public function dispatch(object $event): object
    {
        if ($event instanceof IsAnInternalUserEvent) {
            $event->setIsInternal(true);
        }

        return $event;
    }
}

#[AllowMockObjectsWithoutExpectations]
class ApiKeyControllerTest extends AbstractTest
{
    private const int BASE = 9_017_000;
    private const int TEST_UID = self::BASE + 6;

    /** @var ReflectionClass<ApiKeyController> */
    private ReflectionClass $reflector;
    private TestableApiKeyController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();

        $this->controller = new TestableApiKeyController();
        $this->reflector  = new ReflectionClass(ApiKeyController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = self::TEST_UID;
        $user->email     = 'ctrltest_' . self::BASE . '@example.org';
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, true);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new InternalFeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, Database::obtain());
    }

    /**
     * @throws \PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        Database::obtain()->getConnection()->exec("DELETE FROM api_keys WHERE uid = " . self::TEST_UID);
    }

    /**
     * @throws \PDOException
     */
    private function seedApiKey(string $key = 'ctrlkey_9017000', string $secret = 'ctrlsecret_9017000'): void
    {
        Database::obtain()->getConnection()->exec(
            "INSERT INTO api_keys (uid, api_key, api_secret, create_date, last_update, enabled) "
            . "VALUES (" . self::TEST_UID . ", '$key', '$secret', NOW(), NOW(), 1)"
        );
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    /**
     * @throws ReflectionException
     */
    private function setUserNotAuthenticated(): void
    {
        $user      = new UserStruct();
        $user->uid = null;
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
    }

    // ─── createApiKeyStruct (private) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function createApiKeyStruct_returns_struct_for_current_user(): void
    {
        $struct = $this->invokePrivate('createApiKeyStruct');

        $this->assertInstanceOf(ApiKeyStruct::class, $struct);
        $this->assertSame(self::TEST_UID, $struct->uid);
        $this->assertTrue($struct->enabled);
        $this->assertSame(20, strlen($struct->api_key));
        $this->assertSame(20, strlen($struct->api_secret));
        $this->assertNotSame($struct->api_key, $struct->api_secret);
    }

    // ─── show ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function show_returns_existing_key_with_masked_secret(): void
    {
        $this->seedApiKey('ctrlkey_show_9017000', 'realsecret');

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (ApiKeyStruct $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->show();

        $this->assertInstanceOf(ApiKeyStruct::class, $captured);
        $this->assertSame(self::TEST_UID, $captured->uid);
        $this->assertSame('ctrlkey_show_9017000', $captured->api_key);
        $this->assertSame('***********', $captured->api_secret);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function show_throws_not_found_when_no_key_exists(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('The user has not a valid API key');

        $this->controller->show();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function show_throws_not_found_when_user_not_authenticated(): void
    {
        $this->setUserNotAuthenticated();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->show();
    }

    // ─── create ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_generates_and_returns_new_key_with_secret(): void
    {
        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (ApiKeyStruct $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->responseMock->method('status')->willReturn(new \Klein\HttpStatus(200));

        $this->controller->create();

        $this->assertInstanceOf(ApiKeyStruct::class, $captured);
        $this->assertSame(self::TEST_UID, $captured->uid);
        $this->assertSame(20, strlen($captured->api_key));
        $this->assertSame(20, strlen($captured->api_secret));
        $this->assertNotEmpty($captured->id);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_throws_not_found_when_user_already_has_a_key(): void
    {
        $this->seedApiKey('ctrlkey_dup_9017000', 'dupsecret');
        $this->responseMock->method('status')->willReturn(new \Klein\HttpStatus(200));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('The user has not a valid API key');

        $this->controller->create();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function create_throws_authentication_error_when_not_logged_in(): void
    {
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, false);

        $this->expectException(AuthenticationError::class);

        $this->controller->create();
    }

    // ─── delete ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_removes_existing_key(): void
    {
        $this->seedApiKey('ctrlkey_del_9017000', 'delsecret');

        $this->controller->delete();

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->query("SELECT COUNT(*) FROM api_keys WHERE uid = " . self::TEST_UID);
        $this->assertNotFalse($stmt);
        $remain = (int) $stmt->fetchColumn();

        $this->assertSame(0, $remain);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_throws_not_found_when_no_key_exists(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('The user has not a valid API key');

        $this->controller->delete();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_throws_authentication_error_when_not_logged_in(): void
    {
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, false);

        $this->expectException(AuthenticationError::class);

        $this->controller->delete();
    }
}
