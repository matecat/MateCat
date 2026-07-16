<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\RequestExportTMXController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use Utils\Engines\Results\MyMemory\ExportResponse;
use Utils\TMS\TMSService;

/**
 * Overrides the createTMSService() seam (added to
 * {@see RequestExportTMXController}) so download()'s success path can be
 * exercised without constructing a real TMSService — which would otherwise
 * reach the MyMemory engine's emailExport() -> AbstractEngine::_call() ->
 * MultiCurlHandler, a real outbound HTTP call with no test-env guard. This
 * mirrors the campaign's sanctioned "refactor construction behind an
 * overridable protected seam, return a createStub" pattern for
 * external-service calls.
 */
class TestableRequestExportTMXController extends RequestExportTMXController
{
    public ?TMSService $tmsServiceStub = null;

    public function __construct()
    {
    }

    protected function createTMSService(): TMSService
    {
        return $this->tmsServiceStub ?? parent::createTMSService();
    }
}

class RequestExportTMXControllerTest extends AbstractTest
{
    /** @var ReflectionClass<RequestExportTMXController> */
    private ReflectionClass $reflector;
    private TestableRequestExportTMXController $controller;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableRequestExportTMXController();
        $this->reflector  = new ReflectionClass(RequestExportTMXController::class);

        $this->setProp('response', new Response());
        $this->setProp('database', obtainTestDatabase());
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/tmx/export', 'REQUEST_METHOD' => 'GET'];
        $this->setProp('request', new Request($params, [], [], $serverParams));
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
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── registerValidators() ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_appends_a_login_validator(): void
    {
        $this->setRequestParams([]);
        $this->setProp('validators', []);

        $this->invokePrivate('registerValidators');

        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);

        $this->assertIsArray($validators);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    // ─── validateTheRequest() ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_and_builds_tms_service(): void
    {
        $this->setRequestParams([
            'id_job'         => '123',
            'password'       => 'pw123',
            'tm_key'         => 'tmkey123',
            'tm_name'        => 'MyTM',
            'downloadToken'  => 'tok-1',
            'email'          => 'user@example.org',
            'strip_tags'     => '1',
            'source'         => 'en-US',
            'target'         => 'it-IT',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('123', $result['id_job']);
        $this->assertSame('pw123', $result['password']);
        $this->assertSame('tmkey123', $result['tm_key']);
        $this->assertSame('MyTM', $result['tm_name']);
        $this->assertSame('tok-1', $result['downloadToken']);
        $this->assertSame('user@example.org', $result['download_to_email']);
        $this->assertTrue($result['strip_tags']);
        $this->assertSame('en-US', $result['source']);
        $this->assertSame('it-IT', $result['target']);
        $this->assertInstanceOf(TMSService::class, $result['tmxHandler']);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_strip_tags_defaults_to_false_when_absent(): void
    {
        $this->setRequestParams([
            'email'   => 'user@example.org',
            'tm_name' => 'MyTM',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertFalse($result['strip_tags']);
    }

    /**
     * FILTER_SANITIZE_EMAIL / FILTER_SANITIZE_SPECIAL_CHARS never return
     * `false` for scalar/null input (only for array input); Klein's
     * Request::param() returns an array when the caller submits the param
     * as an array (e.g. `email[]=a&email[]=b`), which is the genuine,
     * real-input way to reach this guard without faking a failure.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_on_array_valued_email_param(): void
    {
        $this->setRequestParams([
            'email'   => ['a@example.org', 'b@example.org'],
            'tm_name' => 'MyTM',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);
        $this->expectExceptionMessage('Invalid email provided for download.');

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTheRequest_throws_on_array_valued_tm_name_param(): void
    {
        $this->setRequestParams([
            'email'   => 'user@example.org',
            'tm_name' => ['a', 'b'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);
        $this->expectExceptionMessage('Invalid TM name provided.');

        $this->invokePrivate('validateTheRequest');
    }

    // ─── download() ───

    /**
     * download() calls validateTheRequest() as its first statement; an
     * array-valued `email` param makes that call throw before the
     * network-bound TMSService::requestTMXEmailDownload() line is ever
     * reached, covering download()'s own body up to (not including) the
     * excluded network call documented in the class docblock above.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function download_propagates_validation_failure_before_any_network_call(): void
    {
        $this->setRequestParams([
            'email'   => ['a@example.org', 'b@example.org'],
            'tm_name' => 'MyTM',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->controller->download();
    }

    /**
     * Full success path, via the createTMSService() seam: no real MyMemory
     * engine construction, no outbound HTTP.
     *
     * @throws ReflectionException
     * @throws MockObjectException
     */
    #[Test]
    public function download_returns_json_response_with_export_result(): void
    {
        $user             = new UserStruct();
        $user->email      = 'translator@example.org';
        $user->first_name = 'Ada';
        $user->last_name  = 'Lovelace';
        $this->setProp('user', $user);

        $this->setRequestParams([
            'tm_key'     => 'tmkey123',
            'tm_name'    => 'MyTM',
            'strip_tags' => '1',
        ]);

        $exportResponse = $this->createStub(ExportResponse::class);

        $tmsServiceStub = $this->createStub(TMSService::class);
        $tmsServiceStub->method('requestTMXEmailDownload')->willReturn($exportResponse);

        $this->controller->tmsServiceStub = $tmsServiceStub;

        // download() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->download();
        } finally {
            ob_end_clean();
        }

        /** @var Response $response */
        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        $body     = json_decode((string) $response->body(), true);

        $this->assertIsArray($body);
        $this->assertSame([], $body['errors']);
    }
}
