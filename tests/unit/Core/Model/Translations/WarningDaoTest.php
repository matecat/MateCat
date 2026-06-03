<?php


namespace Matecat\Core\Model\Translations;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use Model\Jobs\WarningsCountStruct;
use Model\Translations\WarningDao;
use Model\Warnings\GlobalWarningStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class WarningDaoTest extends AbstractTest
{
    /**
     * @return array{0: WarningDao, 1: \PDOStatement&\PHPUnit\Framework\MockObject\Stub}
     */
    private function createMockedDao(): array
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('setFetchMode')->willReturn(true);
        $stmtStub->method('execute')->willReturn(true);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);

        return [new WarningDao($dbStub), $stmtStub];
    }

    private function makeWarningStruct(string $idSegment, string $errors): GlobalWarningStruct
    {
        $s = new GlobalWarningStruct();
        $s->id_segment = $idSegment;
        $s->serialized_errors_list = $errors;

        return $s;
    }

    // ─── getWarningsByJobIdAndPassword ───

    #[Test]
    public function test_getWarningsByJobIdAndPassword_returns_array_of_structs(): void
    {
        $structs = [
            $this->makeWarningStruct('42', '{"tag_mismatch":"error"}'),
            $this->makeWarningStruct('43', '{"glossary":"warning"}'),
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($structs);

        $result = $dao->getWarningsByJobIdAndPassword(100, 'abc123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(GlobalWarningStruct::class, $result[0]);
        $this->assertSame('42', $result[0]->id_segment);
        $this->assertSame('{"tag_mismatch":"error"}', $result[0]->serialized_errors_list);
        $this->assertSame('43', $result[1]->id_segment);
    }

    #[Test]
    public function test_getWarningsByJobIdAndPassword_returns_empty_array_when_no_warnings(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn([]);

        $result = $dao->getWarningsByJobIdAndPassword(999, 'no-match');

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_getWarningsByJobIdAndPassword_returns_single_warning(): void
    {
        $structs = [
            $this->makeWarningStruct('10', '{"tag_mismatch":"error"}'),
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($structs);

        $result = $dao->getWarningsByJobIdAndPassword(50, 'pass123');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(GlobalWarningStruct::class, $result[0]);
        $this->assertSame('10', $result[0]->id_segment);
    }

    // ─── getWarningsByProjectIds ───

    #[Test]
    public function test_getWarningsByProjectIds_returns_array_of_structs(): void
    {
        $struct = new WarningsCountStruct();
        $struct->count = 3;
        $struct->id_job = 100;
        $struct->password = 'abc123';
        $struct->segment_list = '1,2,3';

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn([$struct]);

        $result = $dao->getWarningsByProjectIds([1, 2]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(WarningsCountStruct::class, $result[0]);
        $this->assertSame(3, $result[0]->count);
        $this->assertSame(100, $result[0]->id_job);
    }

    #[Test]
    public function test_getWarningsByProjectIds_returns_empty_array_for_no_matches(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn([]);

        $result = $dao->getWarningsByProjectIds([999]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_getWarningsByProjectIds_handles_single_project_id(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn([]);

        $result = $dao->getWarningsByProjectIds([42]);

        $this->assertSame([], $result);
    }

    // ─── getErrorsByChunk ───

    #[Test]
    public function test_getErrorsByChunk_returns_count(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetch')->willReturn(['count' => 5]);

        $chunk = new JobStruct();
        $chunk->id = 100;
        $chunk->password = 'abc123';

        $result = $dao->getErrorsByChunk($chunk);

        $this->assertSame(5, $result);
    }

    #[Test]
    public function test_getErrorsByChunk_returns_zero_when_no_errors(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetch')->willReturn(false);

        $chunk = new JobStruct();
        $chunk->id = 100;
        $chunk->password = 'abc123';

        $result = $dao->getErrorsByChunk($chunk);

        $this->assertSame(0, $result);
    }
}
