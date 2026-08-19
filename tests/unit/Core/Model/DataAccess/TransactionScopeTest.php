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

    private function write(int $id): void
    {
        $this->db->getConnection()->exec("INSERT INTO tx_scope_probe VALUES ($id)");
    }

    private function countRows(): int
    {
        return (int)$this->db->getConnection()->query("SELECT COUNT(*) FROM tx_scope_probe")->fetchColumn();
    }
}
