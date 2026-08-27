<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Session;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\PhpSession;

/**
 * The session runtime adapter.
 *
 * Nearly everything here needs its own process. A session cannot be started once the test runner has
 * produced output, and one started in-process would leak into every later test in the same worker —
 * so each test that needs a live session forks and does not inherit global state.
 *
 * The PHP_SESSION_DISABLED branch of start() has no test: reaching it needs a PHP built without
 * session support, which is not something a test can arrange from inside a session-enabled runtime.
 */
class PhpSessionTest extends AbstractTest
{

    #[Test]
    public function isActiveIsFalseWithNoSession(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status(), 'This test assumes no active session.');

        $this->assertFalse(PhpSession::isActive());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function startBeginsASessionAndIsActiveReportsIt(): void
    {
        $this->assertFalse(PhpSession::isActive(), 'precondition: no session yet');

        PhpSession::start();

        $this->assertTrue(PhpSession::isActive());
        $this->assertNotSame('', session_id());
    }

    /**
     * Idempotence is what lets a caller start a session without first knowing whether some earlier
     * code already did. Asserted on the id rather than on the status: restarting would issue a new
     * one and silently strand whatever the first session held.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function startingTwiceKeepsTheFirstSession(): void
    {
        PhpSession::start();

        $id                = session_id();
        $_SESSION['uid']   = 36;

        PhpSession::start();

        $this->assertSame($id, session_id());
        $this->assertSame(36, $_SESSION['uid']);
    }

    /**
     * The settings have to be applied before the session starts — PHP reads them at start time and
     * ignores changes afterwards — which is why they live next to start() rather than wherever a
     * cookie is otherwise configured.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function configureAppliesTheCookieShape(): void
    {
        PhpSession::configure('MATECAT_TEST_SESSION', '.matecat.test');

        $this->assertSame('MATECAT_TEST_SESSION', ini_get('session.name'));
        $this->assertSame('.matecat.test', ini_get('session.cookie_domain'));
        $this->assertSame('1', ini_get('session.cookie_secure'));
        $this->assertSame('1', ini_get('session.cookie_httponly'));
    }

    /**
     * The name reaches the started session rather than merely sitting in the ini table, which is the
     * part a caller depends on: it is the cookie the browser is handed.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function aConfiguredSessionStartsUnderThatName(): void
    {
        PhpSession::configure('MATECAT_TEST_SESSION', '.matecat.test');
        PhpSession::start();

        $this->assertSame('MATECAT_TEST_SESSION', session_name());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function closeEndsTheSessionAndKeepsWhatWasWritten(): void
    {
        PhpSession::start();

        $id              = session_id();
        $_SESSION['uid'] = 36;

        PhpSession::close();

        $this->assertFalse(PhpSession::isActive(), 'the session must no longer be active');

        // Closed, not destroyed: resuming the same id finds the data still there.
        session_id($id);
        PhpSession::start();

        $this->assertSame(36, $_SESSION['uid']);
    }

    /**
     * close() runs from a shutdown function, where there is no guarantee a session was ever started
     * and nowhere useful to report a complaint. It has to stay quiet rather than emit a warning into
     * a response that has already been sent.
     */
    #[Test]
    public function closingWithNoSessionIsSilent(): void
    {
        $raised = null;

        set_error_handler(static function (int $errno, string $errstr) use (&$raised): bool {
            $raised = $errstr;

            return true;
        });

        try {
            PhpSession::close();
        } finally {
            restore_error_handler();
        }

        $this->assertNull($raised);
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    /**
     * Rotation and destruction are exercised against a live session through PhpSessionStore, which is
     * what calls them; this pins the halves that have no active session to act on, and that a caller
     * therefore reaches on any request that never established one.
     */
    #[Test]
    public function rotationAndDestructionAreNoOpsWithNoSession(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status(), 'This test assumes no active session.');

        PhpSession::regenerateId();
        PhpSession::destroy();

        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

}
