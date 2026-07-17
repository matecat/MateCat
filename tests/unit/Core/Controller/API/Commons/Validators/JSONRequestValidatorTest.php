<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JSONRequestValidator;
use Exception;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller stub — pure-logic validator, no DB required.
 */
class JSONRequestValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Pure-logic suite — no DB seeding needed.
 * Reserved ID block base = 9_922_000, owner = ctrltest_9922000@example.org
 */
class JSONRequestValidatorTest extends AbstractTest
{
    private const string OWNER_EMAIL = 'ctrltest_9922000@example.org';

    private JSONRequestValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new JSONRequestValidatorTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function setRequest(array $server): void
    {
        $prop = $this->ctrlRef->getProperty('request');
        $prop->setAccessible(true);
        $prop->setValue(
            $this->controller,
            new Request([], [], [], $server)
        );
    }

    // ─── happy path ─────────────────────────────────────────────────────────

    #[Test]
    public function passes_when_content_type_is_application_json(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'application/json']);

        $validator = new JSONRequestValidator($this->controller);
        $validator->validate();

        // No exception means success — assert we reach this line
        $this->assertTrue(true);
    }

    #[Test]
    public function passes_when_content_type_has_charset_suffix(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'application/json; charset=utf-8']);

        $validator = new JSONRequestValidator($this->controller);
        $validator->validate();

        $this->assertTrue(true);
    }

    // ─── failure paths ───────────────────────────────────────────────────────

    #[Test]
    public function throws_exception_when_content_type_is_missing(): void
    {
        $this->setRequest([]);

        $validator = new JSONRequestValidator($this->controller);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(405);
        $this->expectExceptionMessage('Content type provided not valid (application/json expected)');

        $validator->validate();
    }

    #[Test]
    public function throws_exception_when_content_type_is_text_plain(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'text/plain']);

        $validator = new JSONRequestValidator($this->controller);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(405);

        $validator->validate();
    }

    #[Test]
    public function throws_exception_when_content_type_is_form_urlencoded(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $validator = new JSONRequestValidator($this->controller);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(405);

        $validator->validate();
    }

    // ─── validate() public surface (callback wiring) ─────────────────────────

    #[Test]
    public function validate_calls_success_callback_on_valid_request(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'application/json']);

        $called    = false;
        $validator = new JSONRequestValidator($this->controller);
        $validator->onSuccess(function () use (&$called) {
            $called = true;
        });
        $validator->validate();

        $this->assertTrue($called, 'onSuccess callback must be invoked after successful validation');
    }

    #[Test]
    public function validate_calls_failure_callback_instead_of_throwing(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'text/html']);

        $caught    = null;
        $validator = new JSONRequestValidator($this->controller);
        $validator->onFailure(function (Exception $e) use (&$caught) {
            $caught = $e;
        });
        $validator->validate();

        $this->assertInstanceOf(Exception::class, $caught);
        $this->assertSame(405, $caught->getCode());
    }

    #[Test]
    public function validate_rethrows_when_no_failure_callback_set(): void
    {
        $this->setRequest(['CONTENT_TYPE' => 'text/xml']);

        $validator = new JSONRequestValidator($this->controller);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(405);

        $validator->validate();
    }
}
