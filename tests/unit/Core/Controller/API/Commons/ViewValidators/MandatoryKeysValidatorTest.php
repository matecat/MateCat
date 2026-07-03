<?php

namespace Matecat\Core\Controller\API\Commons\ViewValidators;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\ViewValidators\MandatoryKeysValidator;
use Controller\Exceptions\RenderTerminatedException;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Registry\AppConfig;

/**
 * Minimal controller stub. Extends BaseKleinViewController with empty
 * constructor to avoid the full Klein bootstrap. Overrides setView/render
 * so the validator's failure path can be tested without PHPTAL or HTTP output.
 */
class MandatoryKeysValidatorTestController extends BaseKleinViewController
{
    public string $capturedTemplate = '';
    /** @var array<string, mixed> */
    public array $capturedParams = [];
    public int $capturedCode = 0;

    public function __construct()
    {
    }

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->capturedTemplate = $template_name;
        $this->capturedParams   = $params;
        $this->capturedCode     = $code;
    }

    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }
}

/**
 * Pure-logic suite — no DB required.
 * Reserved ID block base = 9_934_000 (unused; kept for suite identification).
 * Owner email: ctrltest_9934000@example.org (not inserted into DB).
 */
class MandatoryKeysValidatorTest extends AbstractTest
{
    private const string OWNER = 'ctrltest_9934000@example.org';

    private MandatoryKeysValidatorTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MandatoryKeysValidatorTestController();

        // Base::__construct calls $controller->getRequest() which reads $request.
        // Inject a minimal Request so the validator can be constructed.
        $ref = new ReflectionClass(KleinController::class);
        $prop = $ref->getProperty('request');
        $prop->setAccessible(true);
        $prop->setValue(
            $this->controller,
            new Request([], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET'])
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ── happy path: all mandatory keys present ──────────────────────────────

    #[Test]
    public function validate_does_nothing_when_all_mandatory_keys_present(): void
    {
        // The test environment has AppConfig fully initialised, so
        // areMandatoryKeysPresent() returns true — _validate() is a no-op.
        $this->assertNotEmpty(AppConfig::$MANDATORY_KEYS);
        $this->assertTrue(AppConfig::areMandatoryKeysPresent());

        $validator = new MandatoryKeysValidator($this->controller);
        $validator->validate();

        // setView / render were NOT called
        $this->assertSame('', $this->controller->capturedTemplate);
    }

    // ── failure path: a mandatory key is null → setView+render called ───────

    #[Test]
    public function validate_calls_setView_and_render_when_mandatory_key_missing(): void
    {
        $saved = AppConfig::$DB_SERVER;
        AppConfig::$DB_SERVER = null;

        try {
            $validator = new MandatoryKeysValidator($this->controller);

            $this->expectException(RenderTerminatedException::class);
            $validator->validate();
        } finally {
            AppConfig::$DB_SERVER = $saved;
        }
    }

    #[Test]
    public function validate_sets_bad_configuration_template_with_503_code(): void
    {
        $saved = AppConfig::$DB_SERVER;
        AppConfig::$DB_SERVER = null;

        try {
            $validator = new MandatoryKeysValidator($this->controller);

            try {
                $validator->validate();
                $this->fail('Expected RenderTerminatedException was not thrown');
            } catch (RenderTerminatedException) {
                // render() was reached — check what setView captured
            }

            $this->assertSame('badConfiguration.html', $this->controller->capturedTemplate);
            $this->assertSame([], $this->controller->capturedParams);
            $this->assertSame(503, $this->controller->capturedCode);
        } finally {
            AppConfig::$DB_SERVER = $saved;
        }
    }

    // ── areMandatoryKeysPresent: each nullable key drives false ─────────────

    #[Test]
    public function mandatory_keys_check_fails_when_db_database_is_null(): void
    {
        $saved = AppConfig::$DB_DATABASE;
        AppConfig::$DB_DATABASE = null;

        try {
            $this->assertFalse(AppConfig::areMandatoryKeysPresent());
        } finally {
            AppConfig::$DB_DATABASE = $saved;
        }
    }

    #[Test]
    public function mandatory_keys_check_fails_when_db_user_is_null(): void
    {
        $saved = AppConfig::$DB_USER;
        AppConfig::$DB_USER = null;

        try {
            $this->assertFalse(AppConfig::areMandatoryKeysPresent());
        } finally {
            AppConfig::$DB_USER = $saved;
        }
    }

    #[Test]
    public function mandatory_keys_check_fails_when_db_pass_is_null(): void
    {
        $saved = AppConfig::$DB_PASS;
        AppConfig::$DB_PASS = null;

        try {
            $this->assertFalse(AppConfig::areMandatoryKeysPresent());
        } finally {
            AppConfig::$DB_PASS = $saved;
        }
    }
}
