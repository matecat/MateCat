<?php

namespace Matecat\Core\Utils\Session;

use LogicException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Session\SessionStore;
use Utils\Session\StatelessSessionStore;

/**
 * The whole value of this class is that it refuses, so every method is pinned refusing.
 *
 * A future "helpful" change that made any one of these return null instead of throwing would restore
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
        $this->expectException(LogicException::class);
        $this->store->get('anything');
    }

    #[Test]
    public function setRefuses(): void
    {
        $this->expectException(LogicException::class);
        $this->store->set('anything', 'value');
    }

    #[Test]
    public function hasRefuses(): void
    {
        // has() is the one most likely to be "fixed" to return false, which would read as "no session
        // state" rather than "this controller cannot have session state".
        $this->expectException(LogicException::class);
        $this->store->has('anything');
    }

    #[Test]
    public function removeRefuses(): void
    {
        $this->expectException(LogicException::class);
        $this->store->remove('anything');
    }

    #[Test]
    public function regenerateIdRefuses(): void
    {
        $this->expectException(LogicException::class);
        $this->store->regenerateId();
    }

    #[Test]
    public function destroyRefuses(): void
    {
        $this->expectException(LogicException::class);
        $this->store->destroy();
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
            $this->fail('Expected a LogicException.');
        } catch (LogicException $e) {
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
            $this->fail('Expected a LogicException.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('destroy()', $e->getMessage());
            $this->assertStringNotContainsString("''", $e->getMessage());
        }
    }
}
