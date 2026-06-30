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
