<?php

namespace unit\Controllers;

use Controller\API\App\DeleteContributionController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;
use Utils\Engines\AbstractEngine;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\TMSAbstractResponse;

class FakeEngine extends AbstractEngine
{
    public function __construct()
    {
    }

    public static function getConfigurationParameters(): array
    {
        return [];
    }

    public function getConfigStruct(): array
    {
        return [];
    }

    public function delete($_config): bool
    {
        return true;
    }

    public function get(array $_config): GetMemoryResponse
    {
        return new GetMemoryResponse();
    }

    public function set($_config): array
    {
        return [];
    }

    public function update($_config): array
    {
        return [];
    }

    protected function _decode(mixed $rawValue, array $parameters = [], ?string $function = null): array|TMSAbstractResponse
    {
        return [];
    }
}

class TestableDeleteContributionController extends DeleteContributionController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function createTmsEngine(mixed $id_tms): AbstractEngine
    {
        return new FakeEngine();
    }
}

class DeleteContributionControllerTest extends AbstractTest
{
    private DeleteContributionController $controller;
    private ReflectionClass $reflector;
    private Request $requestStub;

    public function setUp(): void
    {
        parent::setUp();
        $this->requestStub = $this->createStub(Request::class);
        $responseMock = $this->createStub(Response::class);

        $this->reflector = new ReflectionClass(DeleteContributionController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $requestProp = $this->reflector->getProperty('request');
        $requestProp->setValue($this->controller, $this->requestStub);

        $responseProp = $this->reflector->getProperty('response');
        $responseProp->setValue($this->controller, $responseMock);

        $featureSet = $this->createStub(FeatureSet::class);
        $featureSetProp = $this->reflector->getProperty('featureSet');
        $featureSetProp->setValue($this->controller, $featureSet);

        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);
    }

    private function invokeMethod(string $name, array $args = []): mixed
    {
        $method = $this->reflector->getMethod($name);

        return $method->invokeArgs($this->controller, $args);
    }

    private function setupRequestParams(array $params): void
    {
        $this->requestStub->method('param')
            ->willReturnCallback(function (string $key) use ($params) {
                return $params[$key] ?? null;
            });
    }

    #[Test]
    public function validateTheRequest_missing_source_lang_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => '', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing source_lang');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_target_lang_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => '',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing target_lang');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_source_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => '', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing source');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_target_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => '', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing target');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_id_job_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing id job');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_password_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => '', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing job password');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_valid_request_returns_correct_structure(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => ' Hello world ', 'tra' => ' Ciao mondo ', 'id_job' => '999',
            'id_match' => '42', 'password' => ' mypass ', 'current_password' => ' currpass ', 'id_translator' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');

        $this->assertSame('en-US', $result['source_lang']);
        $this->assertSame('it-IT', $result['target_lang']);
        $this->assertSame('Hello world', $result['source']);
        $this->assertSame('Ciao mondo', $result['target']);
        $this->assertSame(999, $result['id_job']);
        $this->assertSame('mypass', $result['password']);
        $this->assertSame('currpass', $result['received_password']);

        $idJobProp = $this->reflector->getProperty('id_job');
        $this->assertSame(999, $idJobProp->getValue($this->controller));
    }

    #[Test]
    public function validateTheRequest_with_id_translator(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '42', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => '77',
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertNotNull($result['id_translator']);
    }

    #[Test]
    public function validateTheRequest_numeric_source_is_allowed(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => '0', 'tra' => 'zero', 'id_job' => '999',
            'id_match' => '42', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertSame('0', $result['source']);
    }

    #[Test]
    public function delete_with_valid_job_succeeds(): void
    {
        $testable = new TestableDeleteContributionController();
        $ref = new ReflectionClass(TestableDeleteContributionController::class);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'id_segment' => '1',
                'source_lang' => 'en-GB',
                'target_lang' => 'es-ES',
                'seg' => 'Hello world',
                'tra' => 'Hola mundo',
                'id_job' => '1886428338',
                'id_match' => '12345',
                'password' => 'a90acf203402',
                'current_password' => 'a90acf203402',
                'id_translator' => null,
                default => null,
            };
        });

        $responseStub = $this->createStub(Response::class);
        $responseStub->method('json')->willReturn($responseStub);

        $ref->getProperty('request')->setValue($testable, $requestStub);
        $ref->getProperty('response')->setValue($testable, $responseStub);

        $featureSet = new FeatureSet();
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        $testable->delete();

        $this->assertTrue(true);
    }
}
