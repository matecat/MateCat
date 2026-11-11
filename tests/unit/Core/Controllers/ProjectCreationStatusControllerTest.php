<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

/**
 * Mock-seam unit test (Playbook §2) for {@see ProjectCreationStatusController}.
 *
 * ID block base 9057000 (reserved): base+1 = project id 9057001.
 * Per-suite owner identifier (no real-DB rows seeded by this suite):
 * ctrltest_9057000@example.org
 *
 * The controller has no afterConstruct() override, so no refactor is required.
 * The controller reads only the payload ProjectQueue publishes in Redis and never the database;
 * this suite seeds/clears its own reserved Redis key so the branches of
 * ProjectCreationStatusController::get() are deterministically exercised.
 */

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\V2\ProjectCreationStatusController;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;
use Utils\Redis\RedisHandler;

class TestableProjectCreationStatusController extends ProjectCreationStatusController
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
class ProjectCreationStatusControllerTest extends AbstractTest
{
    private const int ID_PROJECT = 9057001;
    private const string PASSWORD = 'pass9057001xxxx';

    /** @var ReflectionClass<ProjectCreationStatusController> */
    private ReflectionClass $reflector;
    private TestableProjectCreationStatusController $controller;

    private ?int $responseCode = null;
    private mixed $responseJson = null;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearRedisKey();

        $this->controller = new TestableProjectCreationStatusController();
        $this->reflector  = new ReflectionClass(ProjectCreationStatusController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
        $this->captureResponse();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function tearDown(): void
    {
        $this->clearRedisKey();
        parent::tearDown();
    }

    /**
     * @param array<string,mixed> $params
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     */
    private function setRequestParams(array $params): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            fn(string $key) => $params[$key] ?? null
        );
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    /**
     * Installs a Response mock that records the status code and the JSON body it is given.
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     */
    private function captureResponse(): void
    {
        $response = $this->createMock(Response::class);
        $response->method('code')->willReturnCallback(function (int $c) use (&$response) {
            $this->responseCode = $c;
            return $response;
        });
        $response->method('json')->willReturnCallback(function ($data) use (&$response) {
            $this->responseJson = $data;
            return $response;
        });
        $this->reflector->getProperty('response')->setValue($this->controller, $response);
    }

    /**
     * Seeds the reserved Redis key with the payload ProjectQueue would publish.
     *
     * @param array<string,mixed> $result
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private function seedRedisResult(array $result): void
    {
        $redis = (new RedisHandler())->getConnection();
        $redis->set(
            sprintf(ProjectStatus::PROJECT_QUEUE_HASH, self::ID_PROJECT),
            json_encode($result)
        );
        $redis->disconnect();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function clearRedisKey(): void
    {
        $redis = (new RedisHandler())->getConnection();
        $redis->del(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, self::ID_PROJECT));
        $redis->disconnect();
    }

    /**
     * @return array<string, array{array<string,mixed>}>
     */
    public static function publishedPayloads(): array
    {
        return [
            'pending' => [['ppassword' => self::PASSWORD, 'pending' => true]],
            'failed'  => [['ppassword' => self::PASSWORD, 'errors' => [['message' => 'Boom failure', 'code' => 17]]]],
            'created' => [['ppassword' => self::PASSWORD, 'id_project' => self::ID_PROJECT, 'project_name' => 'Reserved Project 9057001']],
        ];
    }

    /**
     * @return array<string, array{array<string,mixed>}>
     */
    public static function payloadsWithoutPassword(): array
    {
        return [
            'no ppassword key' => [['id_project' => self::ID_PROJECT]],
            'null ppassword'   => [['id_project' => self::ID_PROJECT, 'ppassword' => null]],
            'empty payload'    => [[]],
        ];
    }

    // ── get(): validation failure ──────────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function get_throws_when_id_project_is_not_numeric(): void
    {
        $this->setRequestParams(['id_project' => 'not-a-number']);

        try {
            $this->controller->get();
            self::fail('Expected Exception for non-numeric id_project');
        } catch (Exception $e) {
            self::assertSame(-1, $e->getCode());
            self::assertSame('ID project is not a valid integer', $e->getMessage());
        }
    }

    // ── get(): not found ───────────────────────────────────────────────────

    /**
     * An unknown or expired id gets the same answer as a wrong password.
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_throws_not_found_when_no_payload_is_published(): void
    {
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => self::PASSWORD]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Project not found.');

        $this->controller->get();
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    #[DataProvider('publishedPayloads')]
    public function get_throws_not_found_when_password_does_not_match(array $payload): void
    {
        $this->seedRedisResult($payload);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => 'wrongpassword12']);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Project not found.');

        $this->controller->get();
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    #[DataProvider('publishedPayloads')]
    public function get_throws_not_found_when_password_is_missing(array $payload): void
    {
        $this->seedRedisResult($payload);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT]);

        $this->expectException(NotFoundException::class);

        $this->controller->get();
    }

    /**
     * A payload published before the password was stored in it matches no password.
     *
     * @param array<string,mixed> $payload
     *
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    #[DataProvider('payloadsWithoutPassword')]
    public function get_throws_not_found_when_payload_has_no_password(array $payload): void
    {
        $this->seedRedisResult($payload);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => '']);

        $this->expectException(NotFoundException::class);

        $this->controller->get();
    }

    // ── get(): pending → _letsWait() (202) ─────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_returns_wait_payload_while_creation_is_pending(): void
    {
        $this->seedRedisResult(['ppassword' => self::PASSWORD, 'pending' => true]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => self::PASSWORD]);

        $this->controller->get();

        self::assertSame(202, $this->responseCode);
        self::assertIsArray($this->responseJson);
        self::assertSame(202, $this->responseJson['status']);
        self::assertSame('Project in queue. Wait.', $this->responseJson['message']);
    }

    // ── get(): result carries errors ───────────────────────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_throws_error_from_published_result(): void
    {
        $this->seedRedisResult(['ppassword' => self::PASSWORD, 'errors' => [['message' => 'Boom failure', 'code' => 17]]]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => self::PASSWORD]);

        try {
            $this->controller->get();
            self::fail('Expected Exception carrying the published error');
        } catch (Exception $e) {
            self::assertNotInstanceOf(NotFoundException::class, $e);
            self::assertSame(17, $e->getCode());
            self::assertSame('Boom failure', $e->getMessage());
        }
    }

    // ── get(): success → CreationStatus payload (200) ──────────────────────

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws PHPUnitInvalidArgumentException
     * @throws NoPreviousThrowableException
     * @throws Exception
     */
    #[Test]
    public function get_returns_creation_status_payload_on_success(): void
    {
        $this->seedRedisResult([
            'id_project'   => self::ID_PROJECT,
            'ppassword'    => self::PASSWORD,
            'project_name' => 'Reserved Project 9057001',
            'analyze_url'  => 'https://example.org/analyze/9057001',
        ]);
        $this->setRequestParams(['id_project' => (string)self::ID_PROJECT, 'password' => self::PASSWORD]);

        $this->controller->get();

        self::assertNull($this->responseCode);
        self::assertIsArray($this->responseJson);
        self::assertSame(200, $this->responseJson['status']);
        self::assertSame('Project created', $this->responseJson['message']);
        self::assertSame(self::ID_PROJECT, $this->responseJson['id_project']);
        self::assertSame(self::PASSWORD, $this->responseJson['project_pass']);
        self::assertSame('Reserved Project 9057001', $this->responseJson['project_name']);
        self::assertSame('https://example.org/analyze/9057001', $this->responseJson['analyze_url']);
    }
}
