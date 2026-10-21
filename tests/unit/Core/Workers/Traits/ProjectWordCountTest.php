<?php

namespace Matecat\Core\Workers\Traits;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\CapturesNamedLogs;
use Model\DataAccess\IDatabase;
use Monolog\Level;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use RuntimeException;
use Utils\AsyncTasks\Workers\Traits\ProjectWordCount;

class ProjectWordCountTest extends AbstractTest
{
    use CapturesNamedLogs;

    /**
     * The summary query returns its rows as they come from the database.
     *
     * @throws MockObjectException
     */
    #[Test]
    public function summaryReturnsTheRowsOfTheQuery(): void
    {
        $rows = [['id_job' => 9_938_201, 'password' => 'pw', 'TOTAL' => 10]];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['pid' => 9_938_200])->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $this->assertSame($rows, (new ProjectWordCountHost($db))->summary(9_938_200));
    }

    /**
     * When the query fails, the error is logged on the 'fast_analysis' logger and re-thrown as
     * a RuntimeException.
     *
     * @throws MockObjectException
     */
    #[Test]
    public function summaryLogsTheErrorAndThrowsWhenTheQueryFails(): void
    {
        $log = $this->captureLog('fast_analysis');

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('Summary query failed'));

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        // RuntimeException is also the parent of PHPUnit's own failures, so a fail() inside the
        // try would be caught here. Keep the exception and assert on it after the try instead.
        $thrown = null;
        try {
            (new ProjectWordCountHost($db))->summary(9_938_200);
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A failed query must throw');
        $this->assertStringContainsString('Summary query failed', $thrown->getMessage());
        $this->assertTrue($log->hasRecordThatContains('Summary query failed', Level::Error));
    }
}

/**
 * A tiny host for the trait: it only supplies db() and exposes the protected method.
 */
class ProjectWordCountHost
{
    use ProjectWordCount;

    public function __construct(private readonly IDatabase $database)
    {
    }

    protected function db(): IDatabase
    {
        return $this->database;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summary(int $pid): array
    {
        return $this->getProjectSegmentsTranslationSummary($pid);
    }
}
