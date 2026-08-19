<?php

namespace Matecat\Core\Model\DataAccess;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\TransactionAbortedException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Utils\Registry\AppConfig;

#[CoversClass(Database::class)]
#[Group('PersistenceNeeded')]
class DatabaseTest extends AbstractTest
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
    }

    protected function tearDown(): void
    {
        $this->db->close();

        parent::tearDown();
    }

    // ─── getConnection() ────────────────────────────────────────────────────

    #[Test]
    public function getConnectionReturnsPDO(): void
    {
        $conn = $this->db->getConnection();
        $this->assertInstanceOf(PDO::class, $conn);
    }

    #[Test]
    public function getConnectionReturnsSameInstanceOnMultipleCalls(): void
    {
        $conn1 = $this->db->getConnection();
        $conn2 = $this->db->getConnection();
        $this->assertSame($conn1, $conn2);
    }

    #[Test]
    public function getConnectionThrowsPDOExceptionOnBadCredentials(): void
    {
        $badDb = new Database('invalid_host_that_does_not_exist', 'bad', 'bad', 'bad');

        $this->expectException(PDOException::class);
        $badDb->getConnection();
    }

    // ─── Transaction management ─────────────────────────────────────────────

    #[Test]
    public function beginReturnsPDOAndStartsTransaction(): void
    {
        $pdo = $this->db->begin();
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertTrue($pdo->inTransaction());
        $this->db->rollback();
    }

    #[Test]
    public function beginIsIdempotentWhenAlreadyInTransaction(): void
    {
        $pdo1 = $this->db->begin();
        $pdo2 = $this->db->begin();
        $this->assertSame($pdo1, $pdo2);
        $this->assertTrue($pdo2->inTransaction());
        $this->db->rollback();
    }

    #[Test]
    public function commitEndsTransaction(): void
    {
        $this->db->begin();
        $this->db->commit();
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function rollbackEndsTransaction(): void
    {
        $this->db->begin();
        $this->db->rollback();
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function rollbackIsNoOpWhenNotInTransaction(): void
    {
        $this->db->rollback();
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function transactionCommitsOnSuccess(): void
    {
        $result = $this->db->transaction(function () {
            return 42;
        });

        $this->assertSame(42, $result);
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function transactionRollsBackOnException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test failure');

        try {
            $this->db->transaction(function () {
                throw new Exception('test failure');
            });
        } finally {
            $this->assertFalse($this->db->getConnection()->inTransaction());
        }
    }

    // ─── insert() / last_insert() ───────────────────────────────────────────

    #[Test]
    public function insertReturnsLastInsertId(): void
    {
        $data = [
            'name'                      => '__test_db_insert_' . uniqid(),
            'type'                      => 'NONE',
            'class_load'                => 'none',
            'extra_parameters'          => '{}',
            'translate_relative_url'    => '',
            'contribute_relative_url'   => '',
            'delete_relative_url'       => '',
            'others'                    => '{}',
            'active'                    => 0,
        ];

        $mask = array_keys($data);
        $id = $this->db->insert('engines', $data, $mask);

        $this->assertIsString($id);
        $this->assertGreaterThan(0, (int) $id);

        $this->db->getConnection()->exec("DELETE FROM engines WHERE id = " . (int) $id);
    }

    #[Test]
    public function insertWithIgnoreDuplicateDoesNotThrow(): void
    {
        $data = [
            'name'                      => '__test_db_ignore_' . uniqid(),
            'type'                      => 'NONE',
            'class_load'                => 'none',
            'extra_parameters'          => '{}',
            'translate_relative_url'    => '',
            'contribute_relative_url'   => '',
            'delete_relative_url'       => '',
            'others'                    => '{}',
            'active'                    => 0,
        ];

        $mask = array_keys($data);
        $id = $this->db->insert('engines', $data, $mask, true);
        $id2 = $this->db->insert('engines', $data, $mask, true);

        $this->db->getConnection()->exec("DELETE FROM engines WHERE id IN (" . (int) $id . ", " . (int) $id2 . ")");
        $this->assertTrue(true);
    }

    // ─── update() ───────────────────────────────────────────────────────────

    #[Test]
    public function updateReturnsAffectedRows(): void
    {
        $name = '__test_db_update_' . uniqid();
        $data = [
            'name'                      => $name,
            'type'                      => 'NONE',
            'class_load'                => 'none',
            'extra_parameters'          => '{}',
            'translate_relative_url'    => '',
            'contribute_relative_url'   => '',
            'delete_relative_url'       => '',
            'others'                    => '{}',
            'active'                    => 0,
        ];
        $mask = array_keys($data);
        $id = $this->db->insert('engines', $data, $mask);

        $affected = $this->db->update(
            'engines',
            ['active' => 1],
            ['id' => (int) $id]
        );

        $this->assertSame(1, $affected);
        $this->assertSame(1, $this->db->rowCount());

        $this->db->getConnection()->exec("DELETE FROM engines WHERE id = " . (int) $id);
    }

    #[Test]
    public function updateWithNonMatchingWhereReturnsZero(): void
    {
        $affected = $this->db->update(
            'engines',
            ['active' => 1],
            ['id' => 999999999]
        );

        $this->assertSame(0, $affected);
    }

    // ─── buildInsertStatement() ─────────────────────────────────────────────

    #[Test]
    public function buildInsertStatementGeneratesCorrectSQL(): void
    {
        $attrs = ['name' => 'test', 'type' => 'NONE'];
        $mask = ['name', 'type'];

        [$sql, $dupBindValues] = $this->db->buildInsertStatement('engines', $attrs, $mask);

        $this->assertStringContainsString('INSERT', $sql);
        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringContainsString('`type`', $sql);
        $this->assertStringContainsString(':name', $sql);
        $this->assertStringContainsString(':type', $sql);
        $this->assertEmpty($dupBindValues);
    }

    #[Test]
    public function buildInsertStatementWithIgnore(): void
    {
        $attrs = ['name' => 'test'];
        $mask = ['name'];

        [$sql] = $this->db->buildInsertStatement('engines', $attrs, $mask, true);

        $this->assertStringContainsString('INSERT  IGNORE ', $sql);
    }

    #[Test]
    public function buildInsertStatementThrowsOnEmptyTable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('TABLE constant is not defined');

        $this->db->buildInsertStatement('', ['a' => 1]);
    }

    #[Test]
    public function buildInsertStatementThrowsOnIgnoreWithDuplicateKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INSERT IGNORE and ON DUPLICATE KEYS UPDATE are not allowed together');

        $mask = [];
        $this->db->buildInsertStatement('t', ['a' => 1], $mask, true, false, ['a' => 'override']);
    }

    #[Test]
    public function buildInsertStatementWithOnDuplicateKey(): void
    {
        $attrs = ['name' => 'test', 'type' => 'NONE'];
        $mask = ['name', 'type'];
        $onDup = ['name' => 'value'];

        [$sql, $dupBindValues] = $this->db->buildInsertStatement('engines', $attrs, $mask, false, false, $onDup);

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString('VALUES( name )', $sql);
        $this->assertEmpty($dupBindValues);
    }

    #[Test]
    public function buildInsertStatementWithOnDuplicateKeyAndExplicitBindValue(): void
    {
        $attrs = ['name' => 'test', 'type' => 'NONE'];
        $mask = ['name', 'type'];
        $onDup = ['name' => 'some_literal_string'];

        [$sql, $dupBindValues] = $this->db->buildInsertStatement('engines', $attrs, $mask, false, false, $onDup);

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString(':dupUpdate_name', $sql);
        $this->assertArrayHasKey(':dupUpdate_name', $dupBindValues);
        $this->assertSame('some_literal_string', $dupBindValues[':dupUpdate_name']);
    }

    #[Test]
    public function buildInsertStatementNoNullsExcludesNullFields(): void
    {
        $attrs = ['name' => 'test', 'type' => null];
        $mask = ['name', 'type'];

        [$sql] = $this->db->buildInsertStatement('engines', $attrs, $mask, false, true);

        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringNotContainsString('`type`', $sql);
    }

    // ─── nextSequence() ─────────────────────────────────────────────────────

    #[Test]
    public function nextSequenceReturnsArrayOfIds(): void
    {
        $ids = $this->db->nextSequence(Database::SEQ_ID_SEGMENT, 3);

        $this->assertIsArray($ids);
        $this->assertCount(3, $ids);
        $this->assertSame($ids[0] + 1, $ids[1]);
        $this->assertSame($ids[1] + 1, $ids[2]);
    }

    #[Test]
    public function nextSequenceThrowsOnInvalidSequenceName(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Undefined sequence');

        $this->db->nextSequence('nonexistent_sequence');
    }

    #[Test]
    public function nextSequenceIncrementsByOne(): void
    {
        $first = $this->db->nextSequence(Database::SEQ_ID_SEGMENT, 1);
        $second = $this->db->nextSequence(Database::SEQ_ID_SEGMENT, 1);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first[0] + 1, $second[0]);
    }

    // ─── useDb() ────────────────────────────────────────────────────────────

    #[Test]
    public function useDbSwitchesDatabase(): void
    {
        $this->db->useDb(AppConfig::$DB_DATABASE);
        $this->assertTrue($this->db->ping());
    }

    // ─── close() / ping() ───────────────────────────────────────────────────

    #[Test]
    public function closeResetsConnection(): void
    {
        $this->db->getConnection();
        $this->db->close();

        $reflector = new ReflectionClass($this->db);
        $connProp = $reflector->getProperty('connection');
        $this->assertNull($connProp->getValue($this->db));
    }

    #[Test]
    public function pingReturnsTrue(): void
    {
        $this->assertTrue($this->db->ping());
    }

    #[Test]
    public function getConnectionReconnectsAfterClose(): void
    {
        $this->db->getConnection();
        $this->db->close();

        $conn = $this->db->getConnection();
        $this->assertInstanceOf(PDO::class, $conn);
    }

    // ─── onCommit() ─────────────────────────────────────────────────────────

    /**
     * The ordering guarantee callers depend on: nothing scheduled through onCommit() may observe the
     * data before it is visible. Cache invalidation is the motivating case — bust before the commit
     * and a concurrent reader can repopulate from the pre-commit row, leaving a stale value that
     * outlives the commit for the whole TTL.
     */
    #[Test]
    public function onCommitRunsTheCallbackAfterTheCommitAndNotBefore(): void
    {
        $ran = false;

        $this->db->begin();
        $this->db->onCommit(function () use (&$ran): void {
            $ran = true;
        });

        $this->assertFalse($ran, 'must not run while the transaction is still open');

        $this->db->commit();

        $this->assertTrue($ran);
    }

    #[Test]
    public function onCommitDiscardsTheCallbackOnRollback(): void
    {
        $ran = false;

        $this->db->begin();
        $this->db->onCommit(function () use (&$ran): void {
            $ran = true;
        });
        $this->db->rollback();

        $this->assertFalse($ran, 'work queued on writes that were rolled back must not happen');

        // And it must not leak into the next transaction either.
        $this->db->begin();
        $this->db->commit();

        $this->assertFalse($ran);
    }

    /**
     * Callers should not have to know whether a transaction is open, so with none the callback runs
     * straight away — otherwise it would be queued forever and silently never run.
     */
    #[Test]
    public function onCommitRunsImmediatelyWhenNoTransactionIsOpen(): void
    {
        $ran = false;

        $this->db->onCommit(function () use (&$ran): void {
            $ran = true;
        });

        $this->assertTrue($ran);
    }

    /**
     * The commit already succeeded by the time these run, so a failing callback must not turn a
     * completed write into an exception for the caller.
     */
    #[Test]
    public function onCommitSwallowsAndLogsACallbackFailure(): void
    {
        $secondRan = false;

        $this->db->begin();
        $this->db->onCommit(static function (): void {
            throw new Exception('cache invalidation failed');
        });
        $this->db->onCommit(function () use (&$secondRan): void {
            $secondRan = true;
        });

        $this->db->commit();

        $this->assertTrue($secondRan, 'one failing callback must not skip the rest');
    }

    /**
     * A callback that itself defers work must queue for the next transaction rather than extend the
     * drain in progress, or a self-scheduling callback would loop forever inside commit().
     */
    #[Test]
    public function onCommitDoesNotRecurseWhenACallbackSchedulesMoreWork(): void
    {
        $outer = 0;
        $inner = 0;

        $this->db->begin();
        $this->db->onCommit(function () use (&$outer, &$inner): void {
            $outer++;
            // No transaction is open during the drain, so this one runs immediately.
            $this->db->onCommit(function () use (&$inner): void {
                $inner++;
            });
        });
        $this->db->commit();

        $this->assertSame(1, $outer);
        $this->assertSame(1, $inner);
    }

    /**
     * The data is already committed and the caller has been told the write succeeded, so a failure
     * in best-effort work — a cache bust, a message enqueue — must not turn into an exception it
     * cannot act on.
     */
    #[Test]
    public function onCommitSwallowsAFailingBestEffortCallback(): void
    {
        $this->db->begin();
        $this->db->onCommit(static fn() => throw new RuntimeException('redis down'));

        $this->db->commit();

        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    /**
     * The exception to that: work whose silent failure is a correctness or a security problem. A
     * credential sweep that quietly fails leaves a revoked job password answering out of the cache
     * for the whole TTL, and the caller is the only party left that can retry.
     */
    #[Test]
    public function onCommitRethrowsAFailingCriticalCallback(): void
    {
        $this->db->begin();
        $this->db->onCommit(static fn() => throw new RuntimeException('redis down'), true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('redis down');
        $this->db->commit();
    }

    #[Test]
    public function onCommitRunsEveryCallbackBeforeRethrowingACriticalFailure(): void
    {
        $ran = false;

        $this->db->begin();
        $this->db->onCommit(static fn() => throw new RuntimeException('redis down'), true);
        $this->db->onCommit(function () use (&$ran): void {
            $ran = true;
        });

        try {
            $this->db->commit();
            $this->fail('the critical failure must be re-thrown');
        } catch (RuntimeException) {
            // asserted in onCommitRethrowsAFailingCriticalCallback
        }

        $this->assertTrue($ran, 'unrelated queued work has already been paid for and must still run');
    }

    #[Test]
    public function onCommitRethrowsTheFirstCriticalFailureWhenSeveralFail(): void
    {
        $this->db->begin();
        $this->db->onCommit(static fn() => throw new RuntimeException('first'), true);
        $this->db->onCommit(static fn() => throw new RuntimeException('second'), true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('first');
        $this->db->commit();
    }

    /**
     * A scope nested inside this transaction cannot roll it back — it does not own it — so it marks
     * it unable to commit instead. Whoever eventually calls commit() is refused, including a caller
     * that opened the transaction by hand and has never heard of this mechanism.
     */
    #[Test]
    public function commitRefusesWhenTheTransactionWasMarkedRollbackOnly(): void
    {
        $this->db->begin();
        $this->db->markRollbackOnly();

        try {
            $this->db->commit();
            $this->fail('the commit must be refused');
        } catch (TransactionAbortedException) {
            // The refusal has to leave the connection clean, not half-open: the next caller on this
            // connection must not inherit an abandoned transaction.
            $this->assertFalse($this->db->getConnection()->inTransaction());
        }
    }

    #[Test]
    public function aRefusedCommitDiscardsTheDeferralQueue(): void
    {
        $ran = false;

        $this->db->begin();
        $this->db->onCommit(function () use (&$ran): void {
            $ran = true;
        });
        $this->db->markRollbackOnly();

        try {
            $this->db->commit();
        } catch (TransactionAbortedException) {
            // asserted in commitRefusesWhenTheTransactionWasMarkedRollbackOnly
        }

        $this->assertFalse($ran, 'deferred work must not run for a transaction that was rolled back');
    }

    /**
     * The flag belongs to the transaction, not to the connection: once the transaction it condemned
     * is gone, the next one starts clean.
     */
    #[Test]
    public function rollbackClearsTheRollbackOnlyFlag(): void
    {
        $this->db->begin();
        $this->db->markRollbackOnly();
        $this->db->rollback();

        $this->db->begin();
        $this->db->commit();

        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    /**
     * A request that dies between markRollbackOnly() and commit() leaves the flag set on a connection
     * that outlives it — a worker holds its connection across messages. The next real begin() must
     * not inherit someone else's verdict.
     */
    #[Test]
    public function beginClearsTheRollbackOnlyFlagOfAnAbandonedTransaction(): void
    {
        $this->db->begin();
        $this->db->markRollbackOnly();
        $this->db->getConnection()->rollBack(); // an abrupt unwind that bypassed rollback()

        $this->db->begin();
        $this->db->commit();

        $this->assertFalse($this->db->getConnection()->inTransaction());
    }
}
