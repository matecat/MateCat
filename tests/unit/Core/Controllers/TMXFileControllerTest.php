<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\TMXFileController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableTMXFileController extends TMXFileController
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
 * Real-DB suite for {@see TMXFileController}.
 *
 * Reserved ID block (Playbook §4): base 9_010_000 (task N=10).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+6 user/uid.
 * Per-suite owner email: ctrltest_9010000@example.org.
 *
 * The public actions import()/importStatus() both delegate to
 * Utils\TMS\TMSService, which performs external MyMemory engine HTTP calls
 * (uploadFile/addTmxInMyMemory/tmxUploadStatus -> _fileUploadStatus). Those
 * branches are not exercisable in a pure unit test without the external
 * service; they are documented as a hard blocker. The unit-testable surface
 * is the request-validation logic (validateTheRequest) plus validator
 * registration, fully covered below.
 */
#[AllowMockObjectsWithoutExpectations]
class TMXFileControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_010_000;

    /** @var ReflectionClass<TMXFileController> */
    private ReflectionClass $reflector;
    private TestableTMXFileController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $defaultTmKeyBackup = '';

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTmKeyBackup = AppConfig::$DEFAULT_TM_KEY;

        $this->cleanFragments(self::BASE);
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);

        $this->controller = new TestableTMXFileController();
        $this->reflector  = new ReflectionClass(TMXFileController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $owner;
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet());
    }

    protected function tearDown(): void
    {
        AppConfig::$DEFAULT_TM_KEY = $this->defaultTmKeyBackup;
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/app/tmx', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
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

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_appends_a_login_validator(): void
    {
        $instance = $this->reflector->newInstanceWithoutConstructor();

        $this->reflector->getProperty('request')->setValue($instance, new Request());
        $this->reflector->getProperty('response')->setValue($instance, $this->responseMock);
        $this->reflector->getProperty('validators')->setValue($instance, []);

        $this->reflector->getMethod('registerValidators')->invoke($instance);

        $validators = $this->reflector->getProperty('validators')->getValue($instance);

        $this->assertIsArray($validators);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    // ─── validateTheRequest ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_with_explicit_tm_key(): void
    {
        AppConfig::$DEFAULT_TM_KEY = '';

        $this->setRequestParams([
            'name'                 => 'MyMemory.tmx',
            'tm_key'               => 'abc123key',
            'uuid'                 => 'uuid-xyz-1',
            'disable_upload_limit' => 'true',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('MyMemory.tmx', $result['name']);
        $this->assertSame('abc123key', $result['tm_key']);
        $this->assertSame('uuid-xyz-1', $result['uuid']);
        $this->assertTrue($result['disable_upload_limit']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_disable_upload_limit_defaults_to_false(): void
    {
        AppConfig::$DEFAULT_TM_KEY = '';

        $this->setRequestParams([
            'tm_key' => 'somekey',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertFalse($result['disable_upload_limit']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_falls_back_to_default_tm_key_when_empty(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'default-mm-key';

        $this->setRequestParams([
            'name' => 'file.tmx',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('default-mm-key', $result['tm_key']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_when_no_tm_key_and_no_default(): void
    {
        AppConfig::$DEFAULT_TM_KEY = '';

        $this->setRequestParams([
            'name' => 'file.tmx',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_sanitizes_uuid_stripping_high_and_low_chars(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'fallback';

        $this->setRequestParams([
            'uuid' => "clean-uuid\x01\x7f",
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('clean-uuid', $result['uuid']);
    }

    // ─── import() ───

    /**
     * @throws Exception
     */
    #[Test]
    public function import_throws_no_files_received_when_no_file_uploaded(): void
    {
        AppConfig::$DEFAULT_TM_KEY = 'fallback-key';

        $this->setRequestParams([
            'tm_key' => 'somekey',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No files received.');

        $this->controller->import();
    }
}
