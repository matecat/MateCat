<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\ContextUrlSchemaController;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableContextUrlSchemaController extends ContextUrlSchemaController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

class ContextUrlSchemaControllerTest extends AbstractTest
{
    private TestableContextUrlSchemaController $controller;
    private ReflectionClass $reflector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDatabaseMock();

        $this->controller = new TestableContextUrlSchemaController();
        $this->reflector  = new ReflectionClass(ContextUrlSchemaController::class);

        $this->reflector->getProperty('response')->setValue($this->controller, $this->createStub(Response::class));

        $user = new UserStruct();
        $user->uid        = 1;
        $user->email      = 'test@example.org';
        $user->first_name = 'Test';
        $user->last_name  = 'User';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        parent::tearDown();
    }

    #[Test]
    public function schema_returns_decoded_json_matching_schema_file(): void
    {
        $responseMock = $this->createMock(Response::class);
        $this->reflector->getProperty('response')->setValue($this->controller, $responseMock);

        $expected = json_decode(
            file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/segment_context_url.json')
        );

        $responseMock->expects(self::once())
            ->method('json')
            ->with(self::equalTo($expected))
            ->willReturnSelf();

        $this->controller->schema();
    }

    #[Test]
    public function schema_contains_context_url_property(): void
    {
        $schema = json_decode(
            file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/segment_context_url.json'),
            true
        );

        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('context_url', $schema['properties']);
        $this->assertContains('context_url', $schema['required']);
    }

    #[Test]
    public function registerValidators_registers_login_validator(): void
    {
        $controller = new TestableContextUrlSchemaController();
        $ref = new ReflectionClass(ContextUrlSchemaController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(\Klein\Request::class));
        $ref->getProperty('response')->setValue($controller, $this->createStub(Response::class));
        $ref->getProperty('params')->setValue($controller, []);

        $method = $ref->getMethod('registerValidators');
        $method->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
