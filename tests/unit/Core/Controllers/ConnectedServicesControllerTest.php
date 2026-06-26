<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\ConnectedServicesController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see ConnectedServicesController} (Wave 3, N=16).
 *
 * Reserved ID block (Playbook §4): base = 9_016_000.
 *   9016001 project, 9016006 user/uid, 9016011 connected_service.
 * Owner email: ctrltest_9016000@example.org (never the shared test@example.org).
 * Clean ONLY by reserved id; clean-then-seed in setUp(); parent::tearDown() last.
 */
class TestableConnectedServicesController extends ConnectedServicesController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class ConnectedServicesControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_016_000;

    /** @var ReflectionClass<ConnectedServicesController> */
    private ReflectionClass $reflector;
    private TestableConnectedServicesController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedFixtures();

        $this->controller = new TestableConnectedServicesController();
        $this->reflector  = new ReflectionClass(ConnectedServicesController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        // Non-empty api_key so refreshClientSessionIfNotApi() skips session start.
        $this->setProp('api_key', 'unit-test-key');
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws PDOException
     */
    private function seedFixtures(): void
    {
        $this->seedUser(self::BASE, $this->ownerEmail(self::BASE));
        // Seed a connected service with NULL oauth token so the JSON formatter's
        // getDecryptedOauthAccessToken() returns null (no encryption env needed).
        obtainTestDatabase()->getConnection()->exec(
            "INSERT IGNORE INTO connected_services (id, uid, service, remote_id, name, email, oauth_access_token, created_at, is_default) "
            . "VALUES (" . $this->connectedServiceId(self::BASE) . ", " . $this->userId(self::BASE) . ", 'dropbox', "
            . "'remote_" . self::BASE . "', 'CtrlService', '" . $this->ownerEmail(self::BASE) . "', NULL, NOW(), 1)"
        );
    }

    /**
     * @param non-empty-string $name
     *
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/app/connected_services', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
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
     * @throws PDOException
     */
    private function fetchDisabledAt(int $serviceId): ?string
    {
        $stmt = obtainTestDatabase()->getConnection()->prepare(
            "SELECT disabled_at FROM connected_services WHERE id = :id"
        );
        $stmt->execute(['id' => $serviceId]);
        $value = $stmt->fetchColumn();

        return ($value === false || $value === null) ? null : (string) $value;
    }

    // ─── __validateOwnership ───

    /**
     * @throws ReflectionException
     * @throws ExpectationFailedException
     * @throws PHPUnitException
     */
    #[Test]
    public function validateOwnership_loads_struct_for_owned_service(): void
    {
        $this->setRequestParams(['id_service' => (string) $this->connectedServiceId(self::BASE)]);

        $this->invokePrivate('__validateOwnership');

        /** @var ConnectedServiceStruct $struct */
        $struct = $this->reflector->getProperty('connectedServiceStruct')->getValue($this->controller);

        $this->assertInstanceOf(ConnectedServiceStruct::class, $struct);
        $this->assertSame($this->connectedServiceId(self::BASE), (int) $struct->id);
        $this->assertSame('dropbox', $struct->service);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateOwnership_throws_not_found_for_unknown_id(): void
    {
        $this->setRequestParams(['id_service' => '88888888']);

        $this->expectException(NotFoundException::class);

        $this->invokePrivate('__validateOwnership');
    }

    // ─── verify() ───

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ExpectationFailedException
     * @throws PHPUnitException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function verify_runs_without_gdrive_handling_for_non_gdrive_service(): void
    {
        $this->setRequestParams(['id_service' => (string) $this->connectedServiceId(self::BASE)]);

        // Non-gdrive service: __handleGDrive is skipped, no json() call expected.
        $this->responseMock->expects($this->never())->method('json');

        $this->controller->verify();

        /** @var ConnectedServiceStruct $struct */
        $struct = $this->reflector->getProperty('connectedServiceStruct')->getValue($this->controller);
        $this->assertSame('dropbox', $struct->service);
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function verify_throws_not_found_for_unknown_service(): void
    {
        $this->setRequestParams(['id_service' => '77777777']);

        $this->expectException(NotFoundException::class);

        $this->controller->verify();
    }

    // ─── update() ───

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function update_sets_disabled_at_and_returns_connected_service_payload(): void
    {
        $serviceId = $this->connectedServiceId(self::BASE);
        $this->setRequestParams([
            'id_service' => (string) $serviceId,
            'disabled'   => '1',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($serviceId): bool {
                $this->assertArrayHasKey('connected_service', $data);
                $item = $data['connected_service'];
                $this->assertSame($serviceId, $item['id']);
                $this->assertSame('dropbox', $item['service']);
                $this->assertNotNull($item['disabled_at']);
                return true;
            }));

        $this->controller->update();

        $this->assertNotNull($this->fetchDisabledAt($serviceId));
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function update_clears_disabled_at_when_disabled_is_false(): void
    {
        $serviceId = $this->connectedServiceId(self::BASE);

        // Pre-set a disabled_at so we can assert it gets cleared.
        obtainTestDatabase()->getConnection()->exec(
            "UPDATE connected_services SET disabled_at = NOW() WHERE id = $serviceId"
        );

        $this->setRequestParams([
            'id_service' => (string) $serviceId,
            'disabled'   => '0',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertNull($data['connected_service']['disabled_at']);
                return true;
            }));

        $this->controller->update();

        $this->assertNull($this->fetchDisabledAt($serviceId));
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function update_throws_not_found_for_unknown_service(): void
    {
        $this->setRequestParams(['id_service' => '99999999', 'disabled' => '1']);

        $this->expectException(NotFoundException::class);

        $this->controller->update();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    #[Test]
    public function handleGDrive_throws_type_error_when_struct_is_null(): void
    {
        // Directly exercise the null-guard in __handleGDrive.
        $this->reflector->getProperty('connectedServiceStruct')->setValue($this->controller, null);

        $this->expectException(TypeError::class);

        $this->invokePrivate('__handleGDrive');
    }
}
