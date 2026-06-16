<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\IsOwnerInternalUserValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Filter\IsAnInternalUserEvent;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller stub exposing the seams IsOwnerInternalUserValidator touches:
 * getRequest() and getFeatureSet(). The empty ctor bypasses the full KleinController
 * bootstrap; featureSet is injected via the public setFeatureSet() seam.
 */
class IsOwnerInternalUserValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Pure-logic suite for IsOwnerInternalUserValidator.
 * Reserved ID block base = 9_921_000. No DB rows are created by this suite —
 * the validator only dispatches a FeatureSet hook; no DAO is involved.
 * Owner email: ctrltest_9921000@example.org
 */
class IsOwnerInternalUserValidatorTest extends AbstractTest
{
    private const string EMAIL = 'ctrltest_9921000@example.org';

    private IsOwnerInternalUserValidatorTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new IsOwnerInternalUserValidatorTestController();

        $ctrlRef = new ReflectionClass(KleinController::class);
        $p = $ctrlRef->getProperty('request');
        $p->setAccessible(true);
        $p->setValue(
            $this->controller,
            new Request([], [], [], ['REQUEST_URI' => '/api/test', 'REQUEST_METHOD' => 'GET'])
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ─── helpers ───

    private function makeJobStruct(string $owner): JobStruct
    {
        $job = new JobStruct();
        $job->owner = $owner;

        return $job;
    }

    /**
     * Build a FeatureSet mock whose dispatch() returns an IsAnInternalUserEvent
     * pre-configured with the given $isInternal flag.
     */
    private function makeFeatureSetMock(bool $isInternal): FeatureSet
    {
        $event = new IsAnInternalUserEvent(self::EMAIL);
        $event->setIsInternal($isInternal);

        $stub = $this->createStub(FeatureSet::class);
        $stub->method('dispatch')->willReturn($event);

        return $stub;
    }

    // ─── happy path: feature marks user as internal ───

    #[Test]
    public function validates_when_feature_marks_owner_as_internal(): void
    {
        $this->controller->setFeatureSet($this->makeFeatureSetMock(true));

        $job = $this->makeJobStruct(self::EMAIL);
        $validator = new IsOwnerInternalUserValidator($this->controller, $job);

        // must not throw
        $validator->_validate();
        $this->assertTrue(true);
    }

    // ─── failure path: no feature sets isInternal → AuthorizationError ───

    #[Test]
    public function throws_authorization_error_when_owner_is_not_internal(): void
    {
        $this->controller->setFeatureSet($this->makeFeatureSetMock(false));

        $job = $this->makeJobStruct(self::EMAIL);
        $validator = new IsOwnerInternalUserValidator($this->controller, $job);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Forbidden, Lara Think only accepts requests from internal users');

        $validator->_validate();
    }

    // ─── validate() delegates to _validate() and re-throws on failure ───

    #[Test]
    public function validate_public_method_also_throws_authorization_error(): void
    {
        $this->controller->setFeatureSet($this->makeFeatureSetMock(false));

        $job = $this->makeJobStruct(self::EMAIL);
        $validator = new IsOwnerInternalUserValidator($this->controller, $job);

        $this->expectException(AuthorizationError::class);

        $validator->validate();
    }

    // ─── owner email is forwarded to the dispatched event ───

    #[Test]
    public function dispatch_receives_event_with_correct_owner_email(): void
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

        $job = $this->makeJobStruct(self::EMAIL);
        $validator = new IsOwnerInternalUserValidator($this->controller, $job);
        $validator->_validate();

        $this->assertInstanceOf(IsAnInternalUserEvent::class, $capturedEvent);
        $this->assertSame(self::EMAIL, $capturedEvent->getEmail());
    }
}
