<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\InternalUserValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Filter\IsAnInternalUserEvent;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams InternalUserValidator touches:
 * getRequest(), isLoggedIn()/getUser() (from the LoginValidator parent) and
 * getFeatureSet(). featureSet is injected via the public setFeatureSet() seam.
 */
class InternalUserValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Pure-logic suite for InternalUserValidator. No DB rows created — the
 * validator only checks login state and dispatches a FeatureSet hook.
 * Reserved ID block base = 9_922_000. Email: ctrltest_9922000@example.org
 */
class InternalUserValidatorTest extends AbstractTest
{
    private const string EMAIL = 'ctrltest_9922000@example.org';

    private InternalUserValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new InternalUserValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);

        $this->setCtrlProp('request', new Request(
            [], [], [], ['REQUEST_URI' => '/api/test', 'REQUEST_METHOD' => 'GET']
        ));
        $this->setCtrlProp('userIsLogged', true);

        $user = new UserStruct();
        $user->email = self::EMAIL;
        $this->setCtrlProp('user', $user);
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

    private function makeFeatureSetMock(bool $isInternal): FeatureSet
    {
        $event = new IsAnInternalUserEvent(self::EMAIL);
        $event->setIsInternal($isInternal);

        $stub = $this->createStub(FeatureSet::class);
        $stub->method('dispatch')->willReturn($event);

        return $stub;
    }

    // ─── happy path: logged-in user flagged internal ───

    #[Test]
    public function validates_when_logged_in_and_internal(): void
    {
        $this->controller->setFeatureSet($this->makeFeatureSetMock(true));

        $validator = new InternalUserValidator($this->controller);

        // must not throw
        $validator->_validate();
        $this->assertTrue(true);
    }

    // ─── feature does not flag user internal => AuthorizationError ───

    #[Test]
    public function throws_authorization_error_when_user_not_internal(): void
    {
        $this->controller->setFeatureSet($this->makeFeatureSetMock(false));

        $validator = new InternalUserValidator($this->controller);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Forbidden, please contact support for generating a valid API key');

        $validator->_validate();
    }

    // ─── not logged in => parent LoginValidator throws AuthenticationError 401 ───

    #[Test]
    public function throws_authentication_error_when_not_logged_in(): void
    {
        $this->setCtrlProp('userIsLogged', false);

        $validator = new InternalUserValidator($this->controller);

        $this->expectException(AuthenticationError::class);
        $this->expectExceptionCode(401);

        $validator->_validate();
    }

    // ─── logged in but user has no email => AuthenticationError 401 ───

    #[Test]
    public function throws_authentication_error_when_email_is_null(): void
    {
        $user = new UserStruct();
        $user->email = null;
        $this->setCtrlProp('user', $user);

        $validator = new InternalUserValidator($this->controller);

        $this->expectException(AuthenticationError::class);
        $this->expectExceptionCode(401);

        $validator->_validate();
    }

    // ─── owner email is forwarded to the dispatched event ───

    #[Test]
    public function dispatch_receives_event_with_correct_user_email(): void
    {
        $capturedEvent = null;

        $stub = $this->createStub(FeatureSet::class);
        $stub->method('dispatch')
            ->willReturnCallback(function (IsAnInternalUserEvent $event) use (&$capturedEvent): IsAnInternalUserEvent {
                $capturedEvent = $event;
                $event->setIsInternal(true);

                return $event;
            });

        $this->controller->setFeatureSet($stub);

        $validator = new InternalUserValidator($this->controller);
        $validator->_validate();

        $this->assertInstanceOf(IsAnInternalUserEvent::class, $capturedEvent);
        $this->assertSame(self::EMAIL, $capturedEvent->getEmail());
    }
}
