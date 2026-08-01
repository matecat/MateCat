<?php

namespace Matecat\Core\Utils\Session;

use Utils\Session\StatelessSessionViolation;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\SessionStore;
use Utils\Session\StatelessSessionStore;

/**
 * The whole value of this class is that it refuses, so every method is pinned refusing — with one
 * deliberate exception, `keys()`, which is pinned *not* refusing for the reasons documented on it.
 *
 * A future "helpful" change that made any of the others return null instead of throwing would restore
 * exactly the silent failure this class exists to remove: a stateless endpoint reading session state
 * and getting a plausible empty answer.
 */
class StatelessSessionStoreTest extends AbstractTest
{
    private StatelessSessionStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new StatelessSessionStore();
    }

    #[Test]
    public function itIsASessionStore(): void
    {
        // Substitutability is the point: it must be injectable wherever the interface is expected.
        $this->assertInstanceOf(SessionStore::class, $this->store);
    }

    #[Test]
    public function getRefuses(): void
    {
        $this->expectException(StatelessSessionViolation::class);
        $this->store->get('anything');
    }

    #[Test]
    public function setRefuses(): void
    {
        $this->expectException(StatelessSessionViolation::class);
        $this->store->set('anything', 'value');
    }

    #[Test]
    public function hasRefuses(): void
    {
        // has() is the one most likely to be "fixed" to return false, which would read as "no session
        // state" rather than "this controller cannot have session state".
        $this->expectException(StatelessSessionViolation::class);
        $this->store->has('anything');
    }

    #[Test]
    public function removeRefuses(): void
    {
        $this->expectException(StatelessSessionViolation::class);
        $this->store->remove('anything');
    }

    #[Test]
    public function regenerateIdRefuses(): void
    {
        $this->expectException(StatelessSessionViolation::class);
        $this->store->regenerateId();
    }

    #[Test]
    public function destroyRefuses(): void
    {
        $this->expectException(StatelessSessionViolation::class);
        $this->store->destroy();
    }

    /**
     * The reason the refusal is an Error and not an Exception, pinned.
     *
     * Controllers wrap their action bodies in `catch (Exception $e)` and render the message as an
     * ordinary error response. A LogicException from the store would be caught by any handler of
     * that shape and reported as a routine failure, so a stateless controller reaching for the
     * session would be indistinguishable from a bad request and the boundary would enforce nothing.
     * This test fails the moment someone "tidies" the hierarchy back to Exception.
     */
    #[Test]
    public function theRefusalIsNotSwallowedByAnOrdinaryExceptionHandler(): void
    {
        $caughtAsException = false;

        try {
            try {
                $this->store->get('user');
            } catch (\Exception) {
                $caughtAsException = true;
            }
            $this->fail('Expected the refusal to pass straight through catch (Exception).');
        } catch (StatelessSessionViolation) {
            // Exactly right: it escaped the Exception handler and reached us.
        }

        $this->assertFalse($caughtAsException, 'catch (Exception) must not intercept the refusal');
    }

    /**
     * The deliberate exception to the rule above. `keys()` is called by the login-exception logger,
     * which runs inside a `catch` that is itself wrapped in a swallowing `try`/`catch` — so a throw
     * here would not surface anywhere, it would just delete the diagnostic line on api-key requests.
     * Empty is the truthful answer and names nothing.
     */
    #[Test]
    public function keysReturnsEmptyInsteadOfRefusing(): void
    {
        $this->assertSame([], $this->store->keys());
    }

    /**
     * The message has to point at the fix, because this exception surfaces to whoever added the read,
     * not to whoever wrote this class. It names the operation and the key that was wanted.
     */
    #[Test]
    public function theMessageNamesTheOperationAndTheKey(): void
    {
        try {
            $this->store->get('redeem_project');
            $this->fail('Expected a StatelessSessionViolation.');
        } catch (StatelessSessionViolation $e) {
            $this->assertStringContainsString('get', $e->getMessage());
            $this->assertStringContainsString('redeem_project', $e->getMessage());
            $this->assertStringContainsString('declared stateless', $e->getMessage());
        }
    }

    /**
     * Keyless operations must not render an empty pair of quotes where a key would go.
     */
    #[Test]
    public function theMessageOmitsTheKeyWhenThereIsNone(): void
    {
        try {
            $this->store->destroy();
            $this->fail('Expected a StatelessSessionViolation.');
        } catch (StatelessSessionViolation $e) {
            $this->assertStringContainsString('destroy()', $e->getMessage());
            $this->assertStringNotContainsString("''", $e->getMessage());
        }
    }
}
