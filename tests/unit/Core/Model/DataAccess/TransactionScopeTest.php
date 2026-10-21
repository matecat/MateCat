<?php

namespace Matecat\Core\Model\DataAccess;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\TransactionAbortedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Registry\AppConfig;

/**
 * The behavioural contract of IDatabase::transaction(): who owns the transaction, what a nested
 * scope may and may not do, and what happens to the writes when something inside fails.
 */
#[CoversClass(Database::class)]
#[Group('PersistenceNeeded')]
class TransactionScopeTest extends AbstractTest
{
    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Database(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        // Created outside any transaction: CREATE TEMPORARY TABLE cannot be rolled back, but the
        // InnoDB rows written into it can, which is what these tests measure.
        $this->db->getConnection()->exec("CREATE TEMPORARY TABLE IF NOT EXISTS tx_scope_probe (id INT)");
        $this->db->getConnection()->exec("DELETE FROM tx_scope_probe");
    }

    protected function tearDown(): void
    {
        if ($this->db->getConnection()->inTransaction()) {
            $this->db->rollback();
        }

        $this->db->close();

        parent::tearDown();
    }

    #[Test]
    public function aNestedScopeDoesNotCommitTheOuterTransaction(): void
    {
        $this->db->transaction(function (): void {
            $this->write(1);

            $this->db->transaction(function (): void {
                $this->write(2);
            });

            $this->assertTrue(
                $this->db->getConnection()->inTransaction(),
                'the inner scope must not have closed the transaction it was a guest in'
            );
        });

        $this->assertSame(2, $this->countRows());
    }

    #[Test]
    public function aFailureInANestedScopeRollsBackTheWholeTree(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->write(1);

                $this->db->transaction(function (): void {
                    $this->write(2);

                    throw new RuntimeException('inner failed');
                });
            });

            $this->fail('the original exception must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('inner failed', $e->getMessage());
        }

        $this->assertSame(0, $this->countRows(), 'the outer write must be gone too');
    }

    /**
     * Catching the failure does not buy the caller a partial commit. The scope that failed has
     * already condemned the transaction, and commit() enforces that.
     */
    #[Test]
    public function swallowingANestedFailureStillAbortsTheTransaction(): void
    {
        try {
            $this->db->transaction(function (): void {
                $this->write(1);

                try {
                    $this->db->transaction(static function (): void {
                        throw new RuntimeException('inner failed');
                    });
                } catch (RuntimeException) {
                    // The caller decides to carry on. It does not get to keep the writes.
                }
            });

            $this->fail('the commit must be refused');
        } catch (TransactionAbortedException) {
            // expected
        }

        $this->assertSame(0, $this->countRows());
    }

    /**
     * The migration state: an unconverted caller that opens and closes the transaction by hand,
     * wrapped around a converted scope. It is the majority of the codebase until the sweep is done.
     */
    #[Test]
    public function aHandRolledOuterTransactionIsAlsoRefused(): void
    {
        $this->db->begin();
        $this->write(1);

        try {
            $this->db->transaction(static function (): void {
                throw new RuntimeException('inner failed');
            });
        } catch (RuntimeException) {
            // swallowed, exactly as an unconverted caller might
        }

        try {
            $this->db->commit();
            $this->fail('the hand-rolled commit must be refused too');
        } catch (TransactionAbortedException) {
            // expected
        }

        $this->assertSame(0, $this->countRows());
    }

    #[Test]
    public function aScopeEnteredInsideADoomedTransactionRefusesToRun(): void
    {
        $ran = false;

        $this->db->begin();
        $this->db->markRollbackOnly();

        try {
            $this->db->transaction(function () use (&$ran): void {
                $ran = true;
            });

            $this->fail('a scope inside a condemned transaction must refuse');
        } catch (TransactionAbortedException) {
            $this->assertFalse($ran, 'every statement it ran would have been destined for the rollback');
        } finally {
            $this->db->rollback();
        }
    }

    #[Test]
    public function theScopeReturnsTheCallbackValue(): void
    {
        $this->assertSame('value', $this->db->transaction(static fn(): string => 'value'));
    }

    #[Test]
    public function aNestedScopeAlsoReturnsItsCallbackValue(): void
    {
        $outer = $this->db->transaction(fn(): string => $this->db->transaction(static fn(): string => 'inner'));

        $this->assertSame('inner', $outer);
    }

    #[Test]
    public function deferredWorkDrainsOnceAfterTheOutermostCommit(): void
    {
        $order = [];

        $this->db->transaction(function () use (&$order): void {
            $this->db->onCommit(function () use (&$order): void {
                $order[] = 'outer-callback';
            });

            $this->db->transaction(function () use (&$order): void {
                $this->db->onCommit(function () use (&$order): void {
                    $order[] = 'inner-callback';
                });

                $order[] = 'inner-body';
            });

            $order[] = 'outer-body';
        });

        $this->assertSame(
            ['inner-body', 'outer-body', 'outer-callback', 'inner-callback'],
            $order,
            'both callbacks belong to the single real commit, and neither may run twice'
        );
    }

    #[Test]
    public function deferredWorkIsDiscardedWhenTheTreeAborts(): void
    {
        $ran = false;

        try {
            $this->db->transaction(function () use (&$ran): void {
                $this->db->onCommit(function () use (&$ran): void {
                    $ran = true;
                });

                $this->db->transaction(static function (): void {
                    throw new RuntimeException('inner failed');
                });
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertFalse($ran);
    }

    #[Test]
    public function aScopeEnteredAfterTheServerRolledBackIsRefused(): void
    {
        $ran = false;

        try {
            $this->db->transaction(function () use (&$ran): void {
                $this->write(1);
                $this->serverRollsBack();

                $this->db->transaction(function () use (&$ran): void {
                    $ran = true;
                });
            });
            $this->fail('a scope entered after the server ended the transaction must be refused');
        } catch (TransactionAbortedException) {
            // expected
        }

        $this->assertFalse($ran, 'the refused scope must not open a transaction of its own and run');
        $this->assertSame(0, $this->countRows());
        $this->assertFalse($this->db->isInTransactionScope());
    }

    #[Test]
    public function theCommitIsRefusedAfterTheServerRolledBack(): void
    {
        $drained = false;

        try {
            $this->db->transaction(function () use (&$drained): void {
                $this->db->onCommit(function () use (&$drained): void {
                    $drained = true;
                });
                $this->write(1);
                $this->serverRollsBack();
                // Lands in autocommit: the damage the refusal reports, and cannot undo.
                $this->write(2);
            });
            $this->fail('the commit of a transaction the server ended must be refused');
        } catch (TransactionAbortedException) {
            // expected
        }

        $this->assertFalse($drained, 'work deferred against rolled-back writes must not run');
        $this->assertSame(1, $this->countRows());
    }

    #[Test]
    public function aScopeStaysOpenAfterTheServerRolledBack(): void
    {
        $this->db->begin();
        $this->serverRollsBack();

        $this->assertFalse($this->db->getConnection()->inTransaction());
        $this->assertTrue($this->db->isInTransactionScope());

        $this->db->rollback();

        $this->assertFalse($this->db->isInTransactionScope());
    }

    #[Test]
    public function aFixtureScopeOpenedWithBeginMakesTransactionAGuest(): void
    {
        $this->db->begin();

        $this->db->transaction(function (): void {
            $this->write(1);
        });

        $this->assertTrue($this->db->getConnection()->inTransaction(), 'the guest must not commit the fixture scope');

        $this->db->rollback();

        $this->assertSame(0, $this->countRows());
    }

    #[Test]
    public function joiningWithBeginKeepsTheVerdict(): void
    {
        $this->db->begin();
        $this->db->markRollbackOnly();
        $this->db->begin();

        $this->expectException(TransactionAbortedException::class);

        $this->db->commit();
    }

    #[Test]
    public function theScopeEndsWithCommitRollbackAndClose(): void
    {
        $this->db->transaction(function (): void {
            $this->assertTrue($this->db->isInTransactionScope());
        });
        $this->assertFalse($this->db->isInTransactionScope(), 'after commit');

        $this->db->begin();
        $this->db->rollback();
        $this->assertFalse($this->db->isInTransactionScope(), 'after rollback');

        $this->db->begin();
        $this->db->close();
        $this->assertFalse($this->db->isInTransactionScope(), 'after close');
    }

    /**
     * Stands in for the server ending the transaction on its own, as it does on a deadlock (1213) or
     * on a lock wait timeout (1205) under innodb_rollback_on_timeout. The OK packet clears the PDO
     * flag the same way the first statement after that error does.
     */
    private function serverRollsBack(): void
    {
        $this->db->getConnection()->exec('ROLLBACK');
    }

    private function write(int $id): void
    {
        $this->db->getConnection()->exec("INSERT INTO tx_scope_probe VALUES ($id)");
    }

    private function countRows(): int
    {
        return (int)$this->db->getConnection()->query("SELECT COUNT(*) FROM tx_scope_probe")->fetchColumn();
    }
}
