<?php

namespace Matecat\Core\Utils\Session;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\ArraySessionStore;
use Utils\Session\SessionStore;

class ArraySessionStoreTest extends AbstractTest
{
    #[Test]
    public function itIsASessionStore(): void
    {
        $this->assertInstanceOf(SessionStore::class, new ArraySessionStore());
    }

    #[Test]
    public function aValueSurvivesARoundTrip(): void
    {
        $store = new ArraySessionStore();
        $store->set('wanted_url', '/manage');

        $this->assertTrue($store->has('wanted_url'));
        $this->assertSame('/manage', $store->get('wanted_url'));
    }

    #[Test]
    public function anAbsentKeyReadsAsNullRatherThanRaising(): void
    {
        $store = new ArraySessionStore();

        $this->assertNull($store->get('never_written'));
        $this->assertFalse($store->has('never_written'));
    }

    #[Test]
    public function itCanBeSeededSoATestNeedNotLoopSetters(): void
    {
        $store = new ArraySessionStore(['uid' => 36, 'login_csrf' => 'token']);

        $this->assertSame(36, $store->get('uid'));
        $this->assertSame('token', $store->get('login_csrf'));
    }

    #[Test]
    public function removeDropsOnlyTheNamedKey(): void
    {
        $store = new ArraySessionStore(['a' => 1, 'b' => 2]);
        $store->remove('a');

        $this->assertFalse($store->has('a'));
        $this->assertSame(2, $store->get('b'));
    }

    #[Test]
    public function removingAnAbsentKeyIsANoOp(): void
    {
        $store = new ArraySessionStore(['b' => 2]);
        $store->remove('not_there');

        $this->assertSame(['b' => 2], $store->all());
    }

    #[Test]
    public function clearDiscardsTheContents(): void
    {
        $store = new ArraySessionStore(['a' => 1, 'b' => 2]);
        $store->clear();

        $this->assertSame([], $store->all());
    }

    #[Test]
    public function destroyClearsEverything(): void
    {
        $store = new ArraySessionStore(['a' => 1, 'b' => 2]);
        $store->destroy();

        $this->assertSame([], $store->all());
    }

    /**
     * Rotation has no id to rotate here, so the only way a test can pin the fixation defence is by
     * counting requests. Without this counter, "did login rotate the session id" is unassertable
     * against the double.
     */
    #[Test]
    public function rotationIsCountedSoItCanBeAsserted(): void
    {
        $store = new ArraySessionStore();
        $this->assertSame(0, $store->regenerationCount());

        $store->regenerateId();
        $store->regenerateId();

        $this->assertSame(2, $store->regenerationCount());
    }

    /**
     * Rotation keeps the contents — that is what distinguishes it from destroy().
     */
    #[Test]
    public function rotationDoesNotDiscardTheContents(): void
    {
        $store = new ArraySessionStore(['uid' => 36]);
        $store->regenerateId();

        $this->assertSame(36, $store->get('uid'));
    }

    /**
     * Two stores must not share state. This is the property that removes the tearDown() reset that
     * $_SESSION-based tests need, so it is worth pinning rather than assuming.
     */
    #[Test]
    public function instancesAreIsolatedFromEachOther(): void
    {
        $first  = new ArraySessionStore();
        $second = new ArraySessionStore();

        $first->set('leak', 'yes');

        $this->assertFalse($second->has('leak'));
    }

    #[Test]
    public function keysNamesEveryStoredKey(): void
    {
        $store = new ArraySessionStore();
        $store->set('login_csrf', 'token');
        $store->set('wanted_url', '/dashboard');

        $this->assertSame(['login_csrf', 'wanted_url'], $store->keys());
    }

    /**
     * The whole point of keys() is that it names keys without exposing what is in them — it is the
     * CWE-532 replacement for dumping the session into the login-exception log.
     */
    #[Test]
    public function keysNeverExposesAValue(): void
    {
        $store = new ArraySessionStore(['user' => 'a-password-hash']);

        $this->assertSame(['user'], $store->keys());
    }

    #[Test]
    public function keysIsEmptyOnAFreshStore(): void
    {
        $this->assertSame([], (new ArraySessionStore())->keys());
    }

    #[Test]
    public function keysForgetsARemovedKey(): void
    {
        $store = new ArraySessionStore(['gone' => 1]);
        $store->remove('gone');

        $this->assertSame([], $store->keys());
    }
}
