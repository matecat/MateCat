<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\AjaxUtilsController;
use Controller\API\Commons\Validators\LoginValidator;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Utils\Engines\MyMemory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableAjaxUtilsController extends AjaxUtilsController
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
 * Test double for the MyMemory engine (id=1, hardcoded inside
 * {@see \Utils\TMS\TMSService::__construct}). Bypasses
 * {@see MyMemory::__construct}'s HTTP-touching parent init (mirrors the
 * established {@see \Matecat\Core\Workers\TMAnalysisV2\FakeTMEngine} pattern)
 * and overrides {@see MyMemory::checkCorrectKey()} so
 * {@see AjaxUtilsController::checkTMKey()}'s success/failure branches are
 * exercised with no live network call.
 */
class FakeCheckKeyMyMemory extends MyMemory
{
    public static ?bool $fakeKeyCheckResult = true;

    public function __construct(mixed $engineRecord, \Model\DataAccess\IDatabase $database)
    {
        // Intentionally skip AbstractEngine/MyMemory parent construction:
        // it would require a live HTTP-capable curl handler setup.
        unset($engineRecord, $database);
    }

    public function checkCorrectKey(string $apiKey): ?bool
    {
        return self::$fakeKeyCheckResult;
    }
}

/**
 * Mock-seam suite for {@see AjaxUtilsController}.
 *
 * ID block base = 9018000 (task N=18). Pattern: mock seam — this controller has 0 DAOs;
 * {@see AjaxUtilsController::ping()} runs `SELECT 1` against the DB singleton (stubbed via
 * AbstractTest::createDatabaseMock), {@see AjaxUtilsController::checkTMKey()} and
 * {@see AjaxUtilsController::clearNotCompletedUploads()} are request/session logic.
 * No real-DB rows are seeded; per-suite owner identity ctrltest_9018000@example.org is unused
 * (kept for registry consistency).
 */
#[AllowMockObjectsWithoutExpectations]
class AjaxUtilsControllerTest extends AbstractTest
{
    /** @var \ReflectionClass<AjaxUtilsController> */
    private \ReflectionClass $reflector;
    private TestableAjaxUtilsController $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // ID block base 9018000: mock-seam suite, no DB rows seeded. The stubbed
        // PDOStatement returned by createDatabaseMock answers execute() by default.
        [$dbStub] = $this->createDatabaseMock();

        $this->controller = new TestableAjaxUtilsController();
        $this->reflector  = new \ReflectionClass(AjaxUtilsController::class);

        $this->responseMock = $this->createMock(Response::class);
        $this->setProp('response', $this->responseMock);
        $this->setProp('request', new Request());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('database', $dbStub);
    }

    protected function tearDown(): void
    {
        // createDatabaseMock() set databaseMockApplied; parent::tearDown() restores the singleton.
        parent::tearDown();
    }

    /**
     * @throws \ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws \ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $this->setProp('request', new Request($params));
    }

    // ─── ping() ───

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\Exception
     * @throws \PDOException
     */
    #[Test]
    public function ping_returns_ok_payload_with_timestamp(): void
    {
        // The PDOStatement stub returned by createDatabaseMock answers execute() with its
        // default; ping() only needs prepare()->execute() to succeed before emitting JSON.
        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $before = time();
        $this->controller->ping();
        $after = time();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('data', $captured);
        $this->assertSame('OK', $captured['data'][0]);
        $this->assertIsInt($captured['data'][1]);
        $this->assertGreaterThanOrEqual($before, $captured['data'][1]);
        $this->assertLessThanOrEqual($after, $captured['data'][1]);
    }

    // ─── checkTMKey() ───

    /**
     * @throws \Exception
     */
    #[Test]
    public function checkTMKey_throws_when_key_missing(): void
    {
        $this->setRequestParams(['tm_key' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-9);
        $this->expectExceptionMessage('TM key not provided.');

        $this->controller->checkTMKey();
    }

    /**
     * A whitespace/control-only key is stripped to empty by the FILTER_FLAG_STRIP_LOW
     * sanitiser, exercising the same empty-key guard without a network round-trip.
     *
     * @throws \Exception
     */
    #[Test]
    public function checkTMKey_throws_when_key_is_control_chars_only(): void
    {
        $this->setRequestParams(['tm_key' => "\x01\x02\x03"]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-9);
        $this->expectExceptionMessage('TM key not provided.');

        $this->controller->checkTMKey();
    }

    /**
     * checkTMKey() success branch: TMSService's ctor hardcodes engine id=1
     * (the real MyMemory engine seeded in the test fixture). We temporarily
     * repoint its `class_load` at {@see FakeCheckKeyMyMemory} — same
     * swap-and-restore technique already used by
     * GetInstanceTest::test_getInstance_with_no_mach_for_engine_class_name —
     * so EnginesFactory::getInstance(1, ...) builds a controllable double
     * instead of firing a live MyMemory HTTP call. Restored + Redis-flushed
     * in `finally` since engine id=1 is shared global fixture data.
     *
     * @throws \Exception
     */
    #[Test]
    public function checkTMKey_returns_success_when_key_is_valid(): void
    {
        // setUp() installed a DB stub via createDatabaseMock(); these tests need
        // the real composition-root connection to swap+restore engine id=1.
        \TestDatabaseProvider::reset();
        $db = obtainTestDatabase();
        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);

        // Raw string interpolation would mangle the FQCN's backslashes (MySQL
        // treats `\` as an escape char in string literals) - bind instead.
        $swapStmt = $db->getConnection()->prepare("UPDATE `engines` SET class_load=? WHERE id=1;");
        $swapStmt->execute([FakeCheckKeyMyMemory::class]);
        $flusher->flushdb();
        FakeCheckKeyMyMemory::$fakeKeyCheckResult = true;

        try {
            $this->setRequestParams(['tm_key' => 'a-valid-tm-key']);
            $this->setProp('database', $db);

            $captured = null;
            $this->responseMock->expects($this->once())
                ->method('json')
                ->with($this->callback(function (array $data) use (&$captured): bool {
                    $captured = $data;
                    return true;
                }));

            $this->controller->checkTMKey();

            $this->assertIsArray($captured);
            $this->assertArrayHasKey('success', $captured);
            $this->assertTrue($captured['success']);
        } finally {
            $swapStmt->execute(['MyMemory']);
            $flusher->flushdb();
        }
    }

    /**
     * checkTMKey() failure branch when the underlying engine reports the key
     * as invalid (`checkCorrectKey()` returns false) — same swap technique.
     *
     * @throws \Exception
     */
    #[Test]
    public function checkTMKey_throws_when_key_is_rejected_by_engine(): void
    {
        // setUp() installed a DB stub via createDatabaseMock(); these tests need
        // the real composition-root connection to swap+restore engine id=1.
        \TestDatabaseProvider::reset();
        $db = obtainTestDatabase();
        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);

        $swapStmt = $db->getConnection()->prepare("UPDATE `engines` SET class_load=? WHERE id=1;");
        $swapStmt->execute([FakeCheckKeyMyMemory::class]);
        $flusher->flushdb();
        FakeCheckKeyMyMemory::$fakeKeyCheckResult = false;

        try {
            $this->setRequestParams(['tm_key' => 'a-rejected-tm-key']);
            $this->setProp('database', $db);

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionCode(-9);
            $this->expectExceptionMessage('TM key is not valid.');

            $this->controller->checkTMKey();
        } finally {
            $swapStmt->execute(['MyMemory']);
            $flusher->flushdb();
        }
    }

    // ─── clearNotCompletedUploads() ───

    /**
     * With no `uid` in the session, Session::__construct returns early (no DAO/storage),
     * and clearSession() simply unsets the file-list keys, returning a success payload.
     *
     * @throws \Exception
     * @throws RuntimeException
     * @throws \TypeError
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function clearNotCompletedUploads_returns_success_payload(): void
    {
        $previousDaemon = AppConfig::$IS_DAEMON_INSTANCE;
        AppConfig::$IS_DAEMON_INSTANCE = false;
        $previousSession = $_SESSION ?? null;
        $_SESSION = [];

        try {
            $captured = null;
            $this->responseMock->expects($this->once())
                ->method('json')
                ->with($this->callback(function (array $data) use (&$captured): bool {
                    $captured = $data;
                    return true;
                }));

            $this->controller->clearNotCompletedUploads();

            $this->assertIsArray($captured);
            $this->assertArrayHasKey('success', $captured);
            $this->assertTrue($captured['success']);
        } finally {
            AppConfig::$IS_DAEMON_INSTANCE = $previousDaemon;
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
        }
    }

    /**
     * clearSession() refuses to run in a CLI/daemon context.
     *
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function clearNotCompletedUploads_throws_in_daemon_context(): void
    {
        $previousDaemon = AppConfig::$IS_DAEMON_INSTANCE;
        AppConfig::$IS_DAEMON_INSTANCE = true;
        $previousSession = $_SESSION ?? null;
        $_SESSION = [];

        try {
            $this->expectException(RuntimeException::class);
            $this->controller->clearNotCompletedUploads();
        } finally {
            AppConfig::$IS_DAEMON_INSTANCE = $previousDaemon;
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
        }
    }

    // ─── registerValidators() ───

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($this->controller);

        /** @var list<object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
