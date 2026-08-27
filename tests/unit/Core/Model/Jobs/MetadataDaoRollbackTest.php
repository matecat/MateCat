<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Jobs\MetadataDao;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

/**
 * set() and bulkSet() run inside a transaction scope so the cache can be evicted after the write.
 * What is pinned here is that a failing write reaches the scope rather than being swallowed, and
 * that neither method closes anything by hand: aborting and rolling back is the scope's job, and
 * TransactionScopeTest pins it against a real connection.
 *
 * The failure is injected at the connection rather than provoked against the live table, so these
 * cases need no database. MetadataDaoRealSqlTest covers the paths that succeed.
 */
class MetadataDaoRollbackTest extends AbstractTest
{
    /**
     * @return array{0: IDatabase, 1: PDOException}
     */
    private function makeFailingDatabase(): array
    {
        $failure = new PDOException('job_metadata write failed');

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willThrowException($failure);

        $dbMock = $this->createMock(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($pdoStub);
        // Runs the body and lets the failure through, which is what the real scope does before it
        // aborts. The abort itself is not re-tested here; it belongs to the scope, not to the DAO.
        $dbMock->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(static fn(callable $work) => $work());

        return [$dbMock, $failure];
    }

    #[Test]
    public function setLetsAFailedWriteReachTheTransactionScope(): void
    {
        [$database, $failure] = $this->makeFailingDatabase();

        try {
            (new MetadataDao($database))->set(12, 'job-pw', 'lock_segments', '1');
            self::fail('set() should have re-thrown the failure');
        } catch (PDOException $e) {
            self::assertSame($failure, $e);
        }
    }

    #[Test]
    public function bulkSetLetsAFailedWriteReachTheTransactionScope(): void
    {
        [$database, $failure] = $this->makeFailingDatabase();

        try {
            (new MetadataDao($database))->bulkSet(12, 'job-pw', [
                'lock_segments' => '1',
                'speech2text' => '0',
            ]);
            self::fail('bulkSet() should have re-thrown the failure');
        } catch (PDOException $e) {
            self::assertSame($failure, $e);
        }
    }

    #[Test]
    public function bulkSetOpensNoTransactionForAnEmptyPayload(): void
    {
        $dbMock = $this->createMock(IDatabase::class);
        $dbMock->expects($this->never())->method('transaction');

        (new MetadataDao($dbMock))->bulkSet(12, 'job-pw', []);
    }
}
