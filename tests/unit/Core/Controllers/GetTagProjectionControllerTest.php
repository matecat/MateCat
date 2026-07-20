<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\GetTagProjectionController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\ErrorResponse;
use Utils\Engines\Results\MyMemory\TagProjectionResponse;
use Utils\Logger\MatecatLogger;

class TestableGetTagProjectionController extends GetTagProjectionController
{
    public ?MyMemory $engineStub = null;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function getEngine(): MyMemory
    {
        return $this->engineStub ?? parent::getEngine();
    }
}

#[AllowMockObjectsWithoutExpectations]
class GetTagProjectionControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_006_000;

    private ReflectionClass $reflector;
    private TestableGetTagProjectionController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $owner = $this->ownerEmail(self::BASE);

        $this->cleanFragments(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, 'tagproj_pw');
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);

        $this->controller = new TestableGetTagProjectionController();
        $this->reflector  = new ReflectionClass(GetTagProjectionController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = 1;
        $user->email     = $owner;
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet(obtainTestDatabase()));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/gettagprojection', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    private function validParams(): array
    {
        return [
            'id_segment'  => (string) $this->segmentId(self::BASE),
            'id_job'      => (string) $this->jobId(self::BASE),
            'password'    => 'tagproj_pw',
            'source'      => 'Hello world',
            'target'      => 'Ciao mondo',
            'suggestion'  => 'Ciao',
            'source_lang' => 'en-US',
            'target_lang' => 'it-IT',
        ];
    }

    // ─── validateTheRequest happy path ───

    #[Test]
    public function validateTheRequest_returns_expected_structure(): void
    {
        $this->setRequestParams($this->validParams());

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame((string) $this->jobId(self::BASE), $result['id_job']);
        $this->assertSame((string) $this->segmentId(self::BASE), $result['id_segment']);
        $this->assertSame('tagproj_pw', $result['password']);
        $this->assertSame('Hello world', $result['source']);
        $this->assertSame('Ciao mondo', $result['target']);
        $this->assertSame('Ciao', $result['suggestion']);
        $this->assertSame('en-US', $result['source_lang']);
        $this->assertSame('it-IT', $result['target_lang']);
    }

    #[Test]
    public function validateTheRequest_sanitizes_id_segment_to_numeric(): void
    {
        $params               = $this->validParams();
        $params['id_segment'] = '  ' . $this->segmentId(self::BASE) . '  ';

        $this->setRequestParams($params);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame((string) $this->segmentId(self::BASE), $result['id_segment']);
    }

    // ─── validateTheRequest failure paths ───

    #[Test]
    public function validateTheRequest_throws_when_source_missing(): void
    {
        $params           = $this->validParams();
        $params['source'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_target_missing(): void
    {
        $params           = $this->validParams();
        $params['target'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_source_lang_missing(): void
    {
        $params                = $this->validParams();
        $params['source_lang'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_target_lang_missing(): void
    {
        $params                = $this->validParams();
        $params['target_lang'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_password_missing(): void
    {
        $params             = $this->validParams();
        $params['password'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-5);

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_id_segment_missing(): void
    {
        $params               = $this->validParams();
        $params['id_segment'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validateTheRequest');
    }

    // ─── call() — drives validation + DAO lookup ───

    #[Test]
    public function call_throws_invalid_argument_when_source_missing(): void
    {
        $params           = $this->validParams();
        $params['source'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->controller->call();
    }

    #[Test]
    public function call_throws_not_found_for_wrong_password(): void
    {
        $params             = $this->validParams();
        $params['password'] = 'wrong_password_zzz';
        $this->setRequestParams($params);

        $this->expectException(NotFoundException::class);

        $this->controller->call();
    }

    #[Test]
    public function call_throws_not_found_for_nonexistent_job(): void
    {
        $params           = $this->validParams();
        $params['id_job'] = '99990001';
        $this->setRequestParams($params);

        $this->expectException(NotFoundException::class);

        $this->controller->call();
    }

    // ─── validateTheRequest — id_job missing branch ───

    #[Test]
    public function validateTheRequest_throws_when_id_job_missing(): void
    {
        $params           = $this->validParams();
        $params['id_job'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);

        try {
            $this->invokePrivate('validateTheRequest');
        } finally {
            \Utils\Registry\AppConfig::$SEND_ERR_MAIL_REPORT = false;
        }
    }

    // ─── registerValidators ───

    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $method = new \ReflectionMethod(GetTagProjectionController::class, 'registerValidators');
        $method->invoke($this->controller);

        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);

        $this->assertNotEmpty($validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
    }

    // ─── call() happy path (stub engine, no live HTTP) ───

    #[Test]
    public function call_returns_translation_on_success(): void
    {
        $this->setRequestParams($this->validParams());

        $realResponse = new Response();
        $this->reflector->getProperty('response')->setValue($this->controller, $realResponse);

        $result               = new TagProjectionResponse(['data' => ['translation' => 'Ciao mondo tag']]);
        $result->responseData = 'Ciao mondo tag';

        $engineStub = $this->createStub(MyMemory::class);
        $engineStub->method('getTagProjection')->willReturn($result);

        $this->controller->engineStub = $engineStub;

        // call() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->call();
        } finally {
            ob_end_clean();
        }

        $body = json_decode((string) $realResponse->body(), true);

        $this->assertSame(0, $body['code']);
        $this->assertSame('Ciao mondo tag', $body['data']['translation']);
    }

    #[Test]
    public function call_throws_external_service_exception_on_engine_error(): void
    {
        $this->setRequestParams($this->validParams());

        $errorResult                 = new TagProjectionResponse(['data' => ['translation' => '']]);
        $errorResult->error          = new ErrorResponse();
        $errorResult->error->message = 'boom';

        $engineStub = $this->createStub(MyMemory::class);
        $engineStub->method('getTagProjection')->willReturn($errorResult);

        $this->controller->engineStub = $engineStub;

        $this->expectException(\Controller\API\Commons\Exceptions\ExternalServiceException::class);

        $this->controller->call();
    }

    // ─── getEngine seam (real construction, no live HTTP) ───

    /**
     * Exercises the production getEngine() seam: engineStub is null, so the Testable
     * subclass delegates to parent::getEngine(), running the real
     * EnginesFactory::getInstance(1, db, MyMemory::class) against the seeded test DB.
     * Construction only loads the MyMemory engine row (id 1); no outbound HTTP fires here.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function getEngine_returns_real_mymemory_instance(): void
    {
        $engine = $this->invokePrivate('getEngine');

        $this->assertInstanceOf(MyMemory::class, $engine);
    }
}
