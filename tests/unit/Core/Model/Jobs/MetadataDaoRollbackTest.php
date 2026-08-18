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
 * set() and bulkSet() open a transaction so the cache can be evicted after the write. When the write
 * fails they have to close it themselves: a worker holds its connection across messages, so a
 * transaction left open here would still be open when the next message starts writing.
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
        // false while openTransaction() decides whether to begin one, true while
        // rollbackTransaction() decides whether there is one to end.
        $pdoStub->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);

        $dbMock = $this->createMock(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($pdoStub);
        $dbMock->expects($this->once())->method('begin');
        $dbMock->expects($this->once())->method('rollback');
        $dbMock->expects($this->never())->method('commit');

        return [$dbMock, $failure];
    }

    #[Test]
    public function setRollsBackAndRethrowsWhenTheWriteFails(): void
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
    public function bulkSetRollsBackAndRethrowsWhenTheWriteFails(): void
    {
        [$database, $failure] = $this->makeFailingDatabase();

        try {
            (new MetadataDao($database))->bulkSet(12, 'job-pw', [
                'lock_segments' => '1',
                'speech2text'   => '0',
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
        $dbMock->expects($this->never())->method('begin');
        $dbMock->expects($this->never())->method('commit');
        $dbMock->expects($this->never())->method('rollback');

        (new MetadataDao($dbMock))->bulkSet(12, 'job-pw', []);
    }
}
