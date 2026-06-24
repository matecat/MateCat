<?php

namespace Matecat\Core\Controller\API\Commons\ViewValidators;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;

/**
 * Minimal stub for BaseKleinViewController.
 *
 * BaseKleinViewController::redirectToWantedUrl() is declared `never` (it always exits).
 * PHPUnit refuses to stub a `never` method to return normally, so we use a hand-written
 * stub that overrides it with a trackable no-op.
 *
 * isLoggedIn() is defined in AuthenticationTrait (used by KleinController) and reads
 * $this->userIsLogged. We override it here so the test controls the return value without
 * touching session/auth internals.
 */
class ViewLoginRedirectValidatorTestController extends BaseKleinViewController
{
    public bool $loginState         = false;
    public bool $redirectWasCalled  = false;
    public ?\Throwable $redirectThrows = null;

    public function __construct()
    {
        // Skip parent constructor (needs Request/Response/session/validators).
        $ref = new \ReflectionClass(KleinController::class);
        $req = new Request(
            [],
            [],
            [],
            ['REQUEST_URI' => '/some/page', 'REQUEST_METHOD' => 'GET']
        );
        $ref->getProperty('request')->setValue($this, $req);
    }

    public function isLoggedIn(): bool
    {
        return $this->loginState;
    }

    public function redirectToWantedUrl(): never
    {
        $this->redirectWasCalled = true;
        if ($this->redirectThrows !== null) {
            throw $this->redirectThrows;
        }
        // Simulate returning without actually calling exit/header.
        // `never` return type means we must either throw or exit; we throw a
        // sentinel so the test can decide whether to catch it.
        throw new RedirectSimulatedException();
    }

    public function index(): void {}
}

/**
 * Sentinel exception thrown by the stub instead of exit — lets tests verify
 * the redirect branch fired without terminating the process.
 */
class RedirectSimulatedException extends \RuntimeException {}

/**
 * Tests for ViewLoginRedirectValidator.
 *
 * Reserved ID block base: 9_935_000  Owner: ctrltest_9935000@example.org
 *
 * Branch analysis
 * ───────────────
 * A) Logged in, no $_SESSION['wanted_url']  → does nothing          ✓ testable
 * B) Logged in, $_SESSION['wanted_url'] set → redirectToWantedUrl() ✓ testable via stub
 * C) NOT logged in                          → line 31 sets SESSION,
 *                                             line 32 calls header(),
 *                                             line 33 calls bare `exit`
 *    Line 33 of ViewLoginRedirectValidator.php is a bare `exit` that terminates
 *    the PHP process. It cannot be intercepted within the same process — PHPUnit
 *    itself would be killed. This branch requires subprocess isolation (proc_open
 *    or runkit) which is outside unit-test scope.
 *    Blocker: ViewLoginRedirectValidator.php line 33 — bare `exit`.
 */
#[AllowMockObjectsWithoutExpectations]
class ViewLoginRedirectValidatorTest extends AbstractTest
{
    private ViewLoginRedirectValidatorTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ViewLoginRedirectValidatorTestController();
        unset($_SESSION['wanted_url']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['wanted_url']);
        parent::tearDown();
    }

    // ─── A: logged in, no wanted_url — validator completes silently ──────────

    #[Test]
    public function passes_silently_when_logged_in_and_no_wanted_url(): void
    {
        $this->controller->loginState = true;
        unset($_SESSION['wanted_url']);

        $validator = new ViewLoginRedirectValidator($this->controller);
        $validator->_validate();

        $this->assertFalse($this->controller->redirectWasCalled);
    }

    // ─── B: logged in with wanted_url — redirectToWantedUrl() must fire ──────

    #[Test]
    public function calls_redirectToWantedUrl_when_logged_in_and_wanted_url_is_set(): void
    {
        $this->controller->loginState = true;
        $_SESSION['wanted_url'] = 'some/previous/page';

        $validator = new ViewLoginRedirectValidator($this->controller);

        $this->expectException(RedirectSimulatedException::class);
        $validator->_validate();
    }

    #[Test]
    public function redirect_stub_sets_redirectWasCalled_flag(): void
    {
        $this->controller->loginState = true;
        $_SESSION['wanted_url'] = 'another/page';

        $validator = new ViewLoginRedirectValidator($this->controller);

        try {
            $validator->_validate();
        } catch (RedirectSimulatedException) {
            // expected
        }

        $this->assertTrue($this->controller->redirectWasCalled);
    }

    // ─── validate() wrapper — success callback fires on happy path ───────────

    #[Test]
    public function validate_wrapper_executes_success_callback_on_happy_path(): void
    {
        $this->controller->loginState = true;
        unset($_SESSION['wanted_url']);

        $validator = new ViewLoginRedirectValidator($this->controller);

        $called = false;
        $validator->onSuccess(function () use (&$called) {
            $called = true;
        });

        $validator->validate();

        $this->assertTrue($called, 'onSuccess callback must fire when _validate() returns normally');
    }

    // ─── validate() wrapper — failure callback catches thrown exception ───────

    #[Test]
    public function validate_wrapper_invokes_failure_callback_on_thrown_exception(): void
    {
        $this->controller->loginState  = true;
        $_SESSION['wanted_url']        = 'fail/path';
        // redirectToWantedUrl throws RuntimeException AFTER the sentinel
        $this->controller->redirectThrows = new \RuntimeException('redirect error', 500);

        $validator = new ViewLoginRedirectValidator($this->controller);

        $caught = null;
        $validator->onFailure(function (\Throwable $e) use (&$caught) {
            $caught = $e;
        });

        $validator->validate();

        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertSame(500, $caught->getCode());
    }
}
