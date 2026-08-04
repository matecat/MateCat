<?php

namespace Matecat\Core\Utils\Session;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\PhpSessionStore;
use Utils\Session\SessionStore;

/**
 * Covers the adapter without ever calling session_start().
 *
 * That is deliberate and is a property of the design, not a shortcut: the read/write methods touch
 * only the `$_SESSION` array, and the two methods that do talk to the session subsystem guard on
 * `session_status()`. So this whole class is reachable in-process, with no `#[RunInSeparateProcess]`
 * and no "headers already sent" interaction — which is exactly the testability argument for having an
 * adapter at all.
 */
class PhpSessionStoreTest extends AbstractTest
{
    /** @var array<string, mixed>|null */
    private ?array $sessionBackup = null;

    private PhpSessionStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        // This is the last file that still has to touch the superglobal, so it is also the last one
        // that has to clean up after itself.
        $this->sessionBackup = $_SESSION ?? null;
        $_SESSION            = [];

        $this->store = new PhpSessionStore();
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }

        parent::tearDown();
    }

    #[Test]
    public function itIsASessionStore(): void
    {
        $this->assertInstanceOf(SessionStore::class, $this->store);
    }

    #[Test]
    public function aWriteLandsInTheSuperglobal(): void
    {
        $this->store->set('wanted_url', '/manage');

        $this->assertSame('/manage', $_SESSION['wanted_url']);
    }

    #[Test]
    public function aReadSeesWhatWasPutThereDirectly(): void
    {
        // Reading state written by not-yet-converted code is the whole point during the migration.
        $_SESSION['login_csrf'] = 'token';

        $this->assertSame('token', $this->store->get('login_csrf'));
        $this->assertTrue($this->store->has('login_csrf'));
    }

    #[Test]
    public function anAbsentKeyReadsAsNull(): void
    {
        $this->assertNull($this->store->get('never_written'));
        $this->assertFalse($this->store->has('never_written'));
    }

    /**
     * A stored null must read as absent, which is the documented limitation of the interface. Pinned
     * so nobody "fixes" get() into something that distinguishes them and changes has()'s meaning.
     */
    #[Test]
    public function aStoredNullIsIndistinguishableFromAbsent(): void
    {
        $_SESSION['explicit_null'] = null;

        $this->assertNull($this->store->get('explicit_null'));
        $this->assertFalse($this->store->has('explicit_null'));
    }

    #[Test]
    public function removeUnsetsOnlyTheNamedKey(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];

        $this->store->remove('a');

        $this->assertArrayNotHasKey('a', $_SESSION);
        $this->assertSame(2, $_SESSION['b']);
    }

    #[Test]
    public function removingAnAbsentKeyIsANoOp(): void
    {
        $_SESSION = ['b' => 2];

        $this->store->remove('not_there');

        $this->assertSame(['b' => 2], $_SESSION);
    }

    #[Test]
    public function keysNamesTheSuperglobalsKeysWithoutTheirValues(): void
    {
        $_SESSION = ['user' => 'a-password-hash', 'login_csrf' => 'token'];

        $this->assertSame(['user', 'login_csrf'], $this->store->keys());
    }

    /**
     * A stateless request never starts a session, so the superglobal is genuinely undefined rather
     * than empty. keys() has to survive that without a warning, because its caller is a logger
     * running inside a catch block.
     */
    #[Test]
    public function keysIsEmptyWhenTheSuperglobalWasNeverInitialised(): void
    {
        unset($_SESSION);

        $this->assertSame([], $this->store->keys());

        $_SESSION = [];
    }

    /**
     * session_destroy() alone leaves the live array populated for the rest of the request, so clearing
     * it is part of the contract rather than a nicety. With no active session the guard skips the
     * destroy and the clear is all that happens — which is what makes this assertable here.
     */
    #[Test]
    public function destroyClearsTheArray(): void
    {
        $_SESSION = ['a' => 1, 'b' => 2];

        $this->store->destroy();

        $this->assertSame([], $_SESSION);
    }

    /**
     * Both session-subsystem methods must be safe to call with no session active: an unguarded
     * session_regenerate_id() emits a warning and returns false, and the guard is what lets a caller
     * invoke this without first asking whether a session exists.
     */
    #[Test]
    public function regenerateIdIsANoOpWithNoActiveSessionAndKeepsTheContents(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status(), 'This test assumes no active session.');

        $_SESSION = ['uid' => 36];

        $this->store->regenerateId();

        $this->assertSame(['uid' => 36], $_SESSION);
    }

    /**
     * The active-session branches of regenerateId() and destroy() — the only two lines here that talk
     * to the session subsystem rather than to the array.
     *
     * A separate process is the only way to reach them: session_start() cannot run once the test
     * runner has produced output, and a session started in-process would leak into every later test in
     * the same worker. Global state is deliberately not preserved, so the child starts clean.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function rotationAndDestructionActOnAnActiveSession(): void
    {
        session_start();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());

        $store       = new PhpSessionStore();
        $originalId  = session_id();
        $store->set('uid', 36);

        $store->regenerateId();

        // A new id, and the contents carried across it — that is what makes this the fixation defence
        // rather than a logout.
        $this->assertNotSame($originalId, session_id());
        $this->assertSame(36, $store->get('uid'));

        $store->destroy();

        $this->assertSame([], $_SESSION);
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    /**
     * The distinction between clear() and destroy(), which is the entire reason clear() exists: it
     * empties the data and leaves the session running, so a caller can write into it immediately
     * afterwards. destroy() ends the session, after which a write neither rotates nor persists —
     * which is why handing a browser to a different user cannot be done with destroy().
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function clearEmptiesTheDataAndLeavesTheSessionUsable(): void
    {
        session_start();

        $_SESSION['cart'] = ['previous-users-items'];

        $store = new PhpSessionStore();
        $store->clear();

        $this->assertSame([], $_SESSION);
        $this->assertSame(PHP_SESSION_ACTIVE, session_status(), 'the session must survive a clear');

        // The write that destroy() would have made impossible.
        $store->set('uid', 42);
        $this->assertSame(42, $store->get('uid'));
    }

    /**
     * The `delete_old_session` argument to session_regenerate_id() is load-bearing: without it the
     * previous id keeps its server-side entry and stays replayable, which makes the rotation
     * cosmetic. Asserted against the stored entry rather than by trusting the argument.
     *
     * Separate from the test above so each covers one thing: this one has to close the session to
     * see what reached the store, which would leave nothing for destroy() to act on.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function rotationDeletesTheOldSessionEntry(): void
    {
        // Skipped rather than silently passing under another save handler, where the on-disk layout
        // asserted below would not apply.
        if (ini_get('session.save_handler') !== 'files') {
            $this->markTestSkipped('old-entry removal is asserted against the files save handler only');
        }

        session_start();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());

        $store    = new PhpSessionStore();
        $oldId    = session_id();
        $savePath = session_save_path() ?: sys_get_temp_dir();

        $store->regenerateId();

        $newId = session_id();
        $this->assertNotSame($oldId, $newId, 'precondition: the id must have rotated');

        session_write_close();

        $this->assertFileExists($savePath . '/sess_' . $newId, 'precondition: the new entry must be on disk');
        $this->assertFileDoesNotExist($savePath . '/sess_' . $oldId, 'the old session entry must be deleted');
    }
}
