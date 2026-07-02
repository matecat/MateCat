<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\HeartBeat;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Registry\AppConfig;

/**
 * HeartBeat controller test (App coverage campaign).
 *
 * No DB seeding: ping() only issues `SELECT 1 FROM DUAL` against a real
 * injected IDatabase, and registerValidators()/ping() touch no rows, so no
 * reserved ID block is needed for this file.
 *
 * The filesystem write in ping() is redirected via AppConfig::$ROOT to a
 * temp directory owned by this test (restored in tearDown), so no writes
 * land inside the repo tree.
 */
class TestableHeartBeat extends HeartBeat
{
    public function __construct()
    {
    }
}

class HeartBeatTest extends AbstractTest
{
    private const string TMP_ROOT = '/Users/hashashiyyin/.claude/jobs/6cfd4064/tmp/heartbeat_root';

    private TestableHeartBeat $controller;

    /** @var ReflectionClass<HeartBeat> */
    private ReflectionClass $reflector;

    private string $originalRoot;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!is_dir(self::TMP_ROOT)) {
            mkdir(self::TMP_ROOT, 0777, true);
        }

        $this->originalRoot = AppConfig::$ROOT;

        $this->controller = new TestableHeartBeat();
        $this->reflector  = new ReflectionClass(HeartBeat::class);

        $this->setProp('request', new Request([], [], [], [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'REQUEST_URI' => '/api/app/heartbeat',
        ]));
        $this->setProp('response', new Response());
        $this->setProp('database', obtainTestDatabase());
        $this->setProp('userIsLogged', false);
    }

    protected function tearDown(): void
    {
        AppConfig::$ROOT = $this->originalRoot;

        $touchFile = self::TMP_ROOT . DIRECTORY_SEPARATOR . 'touch';
        if (file_exists($touchFile)) {
            unlink($touchFile);
        }

        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $p = $this->reflector->getProperty($name);
        $p->setValue($this->controller, $value);
    }

    /**
     * @throws ReflectionException
     */
    private function getProp(string $name): mixed
    {
        $p = $this->reflector->getProperty($name);

        return $p->getValue($this->controller);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function register_validators_appends_whitelist_access_validator(): void
    {
        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($this->controller);

        $validators = $this->getProp('validators');

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\WhitelistAccessValidator::class,
            $validators[0]
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function ping_pings_database_touches_storage_and_renders_json(): void
    {
        AppConfig::$ROOT = self::TMP_ROOT;

        $this->controller->ping();

        /** @var Response $response */
        $response = $this->getProp('response');
        $body     = json_decode((string)$response->body(), true);

        $this->assertFileExists(self::TMP_ROOT . DIRECTORY_SEPARATOR . 'touch');
        $this->assertIsArray($body);
        $this->assertSame('OK', $body['status']);
        $this->assertSame('Pong...', $body['message']);
        $this->assertSame(['uid' => 0], $body['user']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function ping_throws_when_storage_is_unavailable(): void
    {
        // Point ROOT at a non-existent directory so touch() fails.
        AppConfig::$ROOT = self::TMP_ROOT . DIRECTORY_SEPARATOR . 'missing-' . uniqid();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Storage unavailable.');

        $this->controller->ping();
    }
}
