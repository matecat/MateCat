<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams LoginValidator touches:
 * getRequest() (Base ctor) and isLoggedIn().
 */
class LoginValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Pure-logic suite for LoginValidator — only checks login state, no DB.
 */
class LoginValidatorTest extends AbstractTest
{
    private LoginValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new LoginValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);

        $this->setCtrlProp('request', new Request(
            [], [], [], ['REQUEST_URI' => '/api/test', 'REQUEST_METHOD' => 'GET']
        ));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function setCtrlProp(string $name, mixed $value): void
    {
        $c = $this->ctrlRef;
        while ($c !== false && !$c->hasProperty($name)) {
            $c = $c->getParentClass();
        }
        $p = $c->getProperty($name);
        $p->setAccessible(true);
        $p->setValue($this->controller, $value);
    }

    // ─── logged in => passes silently ───

    #[Test]
    public function validates_when_user_is_logged_in(): void
    {
        $this->setCtrlProp('userIsLogged', true);

        $validator = new LoginValidator($this->controller);

        // must not throw
        $validator->_validate();
        $this->assertTrue(true);
    }

    // ─── not logged in => AuthenticationError 401 ───

    #[Test]
    public function throws_authentication_error_when_not_logged_in(): void
    {
        $this->setCtrlProp('userIsLogged', false);

        $validator = new LoginValidator($this->controller);

        $this->expectException(AuthenticationError::class);
        $this->expectExceptionCode(401);

        $validator->_validate();
    }
}
