<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\Base;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;

/**
 * Minimal controller stub exposing getRequest() with the empty ctor seam
 * Base::__construct() needs (it calls $kleinController->getRequest()).
 */
class BaseTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Concrete subclass of the abstract Base, used to exercise the protected
 * _validate()/_executeCallbacks() and the public validate()/onSuccess()/
 * onFailure()/getRequest() seams without instantiating Base directly.
 */
class ConcreteBaseValidator extends Base
{
    public bool $validated = false;
    public bool $shouldThrow = false;

    protected function _validate(): void
    {
        $this->validated = true;
        if ($this->shouldThrow) {
            throw new RuntimeException('boom', 4242);
        }
    }

    /** expose protected _executeCallbacks for direct coverage */
    public function runCallbacks(): void
    {
        $this->_executeCallbacks();
    }

    /** expose constructor variadic args */
    public function getArgs(): array
    {
        return $this->args;
    }
}

/**
 * Pure-logic suite for the abstract Base validator (no DAO / no external IO).
 * Reserved ID block base = 9_933_000. The block is unused by this pure-logic
 * suite (no DB rows are created); owner email ctrltest_9933000@example.org.
 */
class BaseTest extends AbstractTest
{
    private const string EMAIL = 'ctrltest_9933000@example.org';

    private BaseTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new BaseTestController();
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

    private function makeValidator(mixed ...$args): ConcreteBaseValidator
    {
        return new ConcreteBaseValidator($this->controller, ...$args);
    }

    // ─── ctor wires request + variadic args, getRequest() returns it ───

    #[Test]
    public function constructor_stores_request_and_variadic_args(): void
    {
        $validator = $this->makeValidator('a', 7);

        $this->assertInstanceOf(Request::class, $validator->getRequest());
        $this->assertSame(['a', 7], $validator->getArgs());
    }

    // ─── happy path: _validate runs then success callbacks fire in order ───

    #[Test]
    public function validate_runs_validate_then_success_callbacks(): void
    {
        $calls = [];
        $validator = $this->makeValidator();

        $ret = $validator
            ->onSuccess(function () use (&$calls) { $calls[] = 'one'; })
            ->onSuccess(function () use (&$calls) { $calls[] = 'two'; });

        $this->assertSame($validator, $ret, 'onSuccess returns $this for chaining');

        $validator->validate();

        $this->assertTrue($validator->validated);
        $this->assertSame(['one', 'two'], $calls);
    }

    // ─── _executeCallbacks invoked directly ───

    #[Test]
    public function execute_callbacks_runs_all_registered_callbacks(): void
    {
        $hit = 0;
        $validator = $this->makeValidator();
        $validator->onSuccess(function () use (&$hit) { $hit++; });
        $validator->onSuccess(function () use (&$hit) { $hit++; });

        $validator->runCallbacks();

        $this->assertSame(2, $hit);
    }

    // ─── failure with no onFailure callback re-throws the exception ───

    #[Test]
    public function validate_rethrows_when_no_failure_callback(): void
    {
        $validator = $this->makeValidator();
        $validator->shouldThrow = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(4242);

        $validator->validate();
    }

    // ─── failure with onFailure callback swallows + receives the exception ───

    #[Test]
    public function validate_invokes_failure_callback_with_exception(): void
    {
        $captured = null;
        $validator = $this->makeValidator();
        $validator->shouldThrow = true;

        $ret = $validator->onFailure(function (\Throwable $e) use (&$captured) {
            $captured = $e;
        });
        $this->assertSame($validator, $ret, 'onFailure returns $this for chaining');

        // must NOT throw — the failure callback intercepts it
        $validator->validate();

        $this->assertInstanceOf(RuntimeException::class, $captured);
        $this->assertSame(4242, $captured->getCode());
    }

    // ─── onSuccess with non-callable triggers a warning, callback not added ───

    #[Test]
    public function onSuccess_with_invalid_callback_triggers_warning(): void
    {
        $validator = $this->makeValidator();

        $warning = null;
        set_error_handler(function (int $errno, string $msg) use (&$warning): bool {
            $warning = $msg;
            return true;
        }, E_USER_WARNING);

        try {
            $ret = $validator->onSuccess(null);
        } finally {
            restore_error_handler();
        }

        $this->assertSame($validator, $ret);
        $this->assertSame('Invalid callback provided', $warning);

        // no callback registered: executing callbacks is a no-op (no throw)
        $validator->runCallbacks();
        $this->assertTrue(true);
    }

    // ─── onFailure with non-callable triggers a warning, no callback set ───

    #[Test]
    public function onFailure_with_invalid_callback_triggers_warning(): void
    {
        $validator = $this->makeValidator();
        $validator->shouldThrow = true;

        $warning = null;
        set_error_handler(function (int $errno, string $msg) use (&$warning): bool {
            $warning = $msg;
            return true;
        }, E_USER_WARNING);

        try {
            $validator->onFailure(null);
        } finally {
            restore_error_handler();
        }

        $this->assertSame('Invalid callback provided', $warning);

        // since no failure callback was set, validate() must still re-throw
        $this->expectException(RuntimeException::class);
        $validator->validate();
    }
}
