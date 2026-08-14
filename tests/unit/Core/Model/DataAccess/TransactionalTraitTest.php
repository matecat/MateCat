<?php

declare(strict_types=1);

namespace Matecat\Core\Model\DataAccess;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\TransactionalTrait;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;

/**
 * A minimal host for the trait: the trait only requires a database to run the transaction on,
 * and the tests only need to reach the three protected methods.
 */
class TransactionalHost
{
    use TransactionalTrait;

    public function __construct(private readonly IDatabase $db)
    {
    }

    protected function getTransactionalDatabase(): IDatabase
    {
        return $this->db;
    }

    public function open(): void
    {
        $this->openTransaction();
    }

    public function commit(): void
    {
        $this->commitTransaction();
    }

    public function rollback(): void
    {
        $this->rollbackTransaction();
    }
}

/**
 * The trait's whole contract is "commit only what you opened". These tests pin the ways that
 * used to be violated: a flag shared by every instance of the class, and a flag left set by a
 * failure.
 */
class TransactionalTraitTest extends AbstractTest
{

    private bool $inTransaction = false;
    private int $begins = 0;
    private int $commits = 0;
    private int $rollbacks = 0;
    private bool $commitThrows = false;

    private IDatabase $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inTransaction = false;
        $this->begins = 0;
        $this->commits = 0;
        $this->rollbacks = 0;
        $this->commitThrows = false;

        $connection = $this->createStub(PDO::class);
        $connection->method('inTransaction')->willReturnCallback(fn(): bool => $this->inTransaction);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($connection);
        $db->method('begin')->willReturnCallback(function () use ($connection): PDO {
            $this->begins++;
            $this->inTransaction = true;

            return $connection;
        });
        $db->method('commit')->willReturnCallback(function (): void {
            $this->commits++;
            if ($this->commitThrows) {
                throw new PDOException('server has gone away');
            }
            $this->inTransaction = false;
        });
        $db->method('rollback')->willReturnCallback(function (): void {
            $this->rollbacks++;
            $this->inTransaction = false;
        });

        $this->db = $db;
    }

    private function host(): TransactionalHost
    {
        return new TransactionalHost($this->db);
    }

    #[Test]
    public function itOpensAndCommitsWhenNobodyElseHasATransaction(): void
    {
        $host = $this->host();

        $host->open();
        $this->assertEquals(1, $this->begins);

        $host->commit();
        $this->assertEquals(1, $this->commits);
        $this->assertFalse($this->inTransaction);
    }

    #[Test]
    public function itJoinsAnOpenTransactionInsteadOfNestingOne(): void
    {
        $outer = $this->host();
        $outer->open();

        $inner = $this->host();
        $inner->open();

        $this->assertEquals(1, $this->begins);
    }

    /**
     * The one the flag was made per-object for. Two instances of the same class used to share it,
     * so the inner commit ended the outer one's transaction with its work half written.
     */
    #[Test]
    public function anInnerInstanceDoesNotCommitTheOuterTransaction(): void
    {
        $outer = $this->host();
        $outer->open();

        $inner = $this->host();
        $inner->open();
        $inner->commit();

        $this->assertEquals(0, $this->commits);
        $this->assertTrue($this->inTransaction);

        $outer->commit();
        $this->assertEquals(1, $this->commits);
    }

    #[Test]
    public function anInnerInstanceDoesNotRollBackTheOuterTransaction(): void
    {
        $outer = $this->host();
        $outer->open();

        $inner = $this->host();
        $inner->open();
        $inner->rollback();

        $this->assertEquals(0, $this->rollbacks);
        $this->assertTrue($this->inTransaction);
    }

    /**
     * A failure used to leave the flag set for the lifetime of the process, which in a daemon is
     * the lifetime of the worker: the next commit on that class committed whatever transaction was
     * open at the time.
     */
    #[Test]
    public function ownershipDoesNotOutliveTheObjectThatFailed(): void
    {
        $failed = $this->host();
        $failed->open();
        $failed->rollback();

        $this->assertEquals(1, $this->rollbacks);

        $unrelated = $this->host();
        $unrelated->open();

        $bystander = $this->host();
        $bystander->commit();

        $this->assertEquals(0, $this->commits);
        $this->assertTrue($this->inTransaction);
    }

    #[Test]
    public function committingWithoutHavingOpenedAnythingIsANoOp(): void
    {
        $this->host()->commit();
        $this->host()->rollback();

        $this->assertEquals(0, $this->commits);
        $this->assertEquals(0, $this->rollbacks);
    }

    #[Test]
    public function aSecondCommitIssuesNoSecondCommand(): void
    {
        $host = $this->host();
        $host->open();
        $host->commit();
        $host->commit();

        $this->assertEquals(1, $this->commits);
    }

    /**
     * A commit that throws has still ended the object's ownership: retrying it would send a second
     * COMMIT for a transaction that is no longer there, or worse, for the next one.
     */
    #[Test]
    public function aFailedCommitDoesNotLeaveTheObjectOwningTheTransaction(): void
    {
        $host = $this->host();
        $host->open();

        $this->commitThrows = true;

        try {
            $host->commit();
            $this->fail('the commit was expected to throw');
        } catch (PDOException) {
            // expected
        }

        $this->commitThrows = false;
        $host->commit();
        $host->rollback();

        $this->assertEquals(1, $this->commits);
        $this->assertEquals(0, $this->rollbacks);
    }

    /**
     * The transaction can also end without this object being told — a rollback by the caller, or a
     * connection dropped and reopened. Committing then would commit the next unit of work.
     */
    #[Test]
    public function itDoesNotCommitATransactionThatIsAlreadyGone(): void
    {
        $host = $this->host();
        $host->open();

        $this->inTransaction = false;

        $host->commit();

        $this->assertEquals(0, $this->commits);
    }
}
