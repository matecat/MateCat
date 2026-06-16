<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\EngineController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use DomainException;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Engines\Apertium;
use Utils\Logger\MatecatLogger;

/**
 * Testable subclass: neutralises the construction chain so the controller can
 * be assembled via reflection in the test harness (Playbook §1).
 */
class TestableEngineController extends EngineController
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
 * Real-DB controller suite for {@see EngineController}.
 *
 * Reserved ID block (Playbook §4): base = 9_001_000 (task N=1).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+6 uid,
 *   base+20 engine row (suite-local, outside the shared fragment offsets).
 * Owner email: ctrltest_9001000@example.org. Clean ONLY by reserved id.
 */
#[AllowMockObjectsWithoutExpectations]
class EngineControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_001_000;

    /** @var ReflectionClass<EngineController> */
    private ReflectionClass $reflector;
    private TestableEngineController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    private function engineId(): int
    {
        return self::BASE + 20;
    }

    private function uid(): int
    {
        return $this->userId(self::BASE);
    }

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedUser(self::BASE);

        $this->controller = new TestableEngineController();
        $this->reflector  = new ReflectionClass(EngineController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = $this->uid();
        $user->email     = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet());
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
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM engines WHERE id = " . $this->engineId());
        $conn->exec("DELETE FROM engines WHERE uid = " . $this->uid());
        $this->cleanFragments(self::BASE);
    }

    /**
     * @throws \PDOException
     */
    private function seedApertiumEngine(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO engines (id, name, type, description, base_url, translate_relative_url, "
            . "contribute_relative_url, others, class_load, extra_parameters, google_api_compliant_version, penalty, active, uid) "
            . "VALUES (" . $this->engineId() . ", 'CtrlApertium', 'MT', 'Apertium Engine', 'https://api.prompsit.com', "
            . "'apertiumws/', '', '{}', 'Utils\\\\Engines\\\\Apertium', '{\"client_secret\":\"s3cr3t\"}', 2, 14, 1, " . $this->uid() . ")"
        );
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
     * @param array<string, mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/engine', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
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

    // ─── validateTheRequest ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape(): void
    {
        $this->setRequestParams([
            'id'       => '42',
            'name'     => 'MyEngine',
            'provider' => EngineConstants::APERTIUM,
            'data'     => json_encode(['secret' => 'abc']),
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('42', $result['id']);
        $this->assertSame('MyEngine', $result['name']);
        $this->assertSame(EngineConstants::APERTIUM, $result['provider']);
        $this->assertSame(['secret' => 'abc'], $result['data']);
    }

    // ─── add() failure guards ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function add_throws_when_name_is_empty(): void
    {
        $this->setRequestParams([
            'name'     => '',
            'provider' => EngineConstants::APERTIUM,
            'data'     => json_encode(['secret' => 'x']),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->controller->add();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function add_throws_when_data_is_empty(): void
    {
        $this->setRequestParams([
            'name'     => 'MyEngine',
            'provider' => EngineConstants::APERTIUM,
            'data'     => '',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-7);

        $this->controller->add();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function add_throws_when_provider_is_empty(): void
    {
        $this->setRequestParams([
            'name'     => 'MyEngine',
            'provider' => '',
            'data'     => json_encode(['secret' => 'x']),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-8);

        $this->controller->add();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function add_throws_domain_exception_for_unknown_provider(): void
    {
        $this->setRequestParams([
            'name'     => 'MyEngine',
            'provider' => 'NotARealProvider',
            'data'     => json_encode(['secret' => 'x']),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(-4);

        $this->controller->add();
    }

    // ─── add() happy path (Apertium has no external validator) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function add_creates_apertium_engine_and_returns_payload(): void
    {
        $this->setRequestParams([
            'name'     => 'CtrlAddApertium',
            'provider' => EngineConstants::APERTIUM,
            'data'     => json_encode(['secret' => 'topsecret']),
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload) use (&$captured): bool {
                $captured = $payload;
                return true;
            }));

        $this->controller->add();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('data', $captured);
        $this->assertArrayHasKey('errors', $captured);
        $this->assertSame([], $captured['errors']);
        $this->assertSame('CtrlAddApertium', $captured['data']['name']);
        $this->assertSame('Apertium', $captured['data']['engine_type']);
        $this->assertNotEmpty($captured['data']['id']);
        $this->assertSame('topsecret', $captured['data']['extra']['client_secret']);
    }

    // ─── disable() ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function disable_throws_when_id_is_empty(): void
    {
        $this->setRequestParams(['id' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-5);

        $this->controller->disable();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function disable_throws_runtime_exception_when_engine_not_found(): void
    {
        $this->setRequestParams(['id' => (string) $this->engineId()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(-9);

        $this->controller->disable();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function disable_returns_engine_id_on_success(): void
    {
        $this->seedApertiumEngine();

        $this->setRequestParams(['id' => (string) $this->engineId()]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload) use (&$captured): bool {
                $captured = $payload;
                return true;
            }));

        $this->controller->disable();

        $this->assertIsArray($captured);
        $this->assertSame([], $captured['errors']);
        $this->assertSame($this->engineId(), $captured['data']['id']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function disable_creation_failed_engine_struct_guard(): void
    {
        // Engine row exists but belongs to a different uid → disable() UPDATE
        // matches no row → returns null → RuntimeException(-9).
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO engines (id, name, type, description, base_url, translate_relative_url, "
            . "others, class_load, extra_parameters, google_api_compliant_version, penalty, active, uid) "
            . "VALUES (" . $this->engineId() . ", 'OtherUserEngine', 'MT', 'x', 'https://api.prompsit.com', "
            . "'apertiumws/', '{}', 'Utils\\\\Engines\\\\Apertium', '{}', 2, 14, 1, " . ($this->uid() + 999) . ")"
        );

        $this->setRequestParams(['id' => (string) $this->engineId()]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(-9);

        try {
            $this->controller->disable();
        } finally {
            $conn->exec("DELETE FROM engines WHERE id = " . $this->engineId());
        }
    }

    // ─── destroyUserEnginesCache (private) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function destroyUserEnginesCache_runs_without_error_for_user(): void
    {
        $result = $this->invokePrivate('destroyUserEnginesCache');

        $this->assertNull($result);
    }
}
