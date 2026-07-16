<?php


namespace Matecat\Core\Model\TMSService;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\TMSService\TMSServiceDao;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Constants\TranslationStatus;

class TMSServiceDaoTest extends AbstractTest
{
    /**
     * @return array{0: TMSServiceDao, 1: \PDOStatement&\PHPUnit\Framework\MockObject\Stub}
     */
    private function createMockedDao(): array
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('setFetchMode')->willReturn(true);
        $stmtStub->method('execute')->willReturn(true);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);

        return [new TMSServiceDao($dbStub), $stmtStub];
    }

    private function createFailingDao(): TMSServiceDao
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->method('setFetchMode')->willReturn(true);
        $stmtStub->method('execute')->willThrowException(new PDOException('DB connection failed'));

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);

        return new TMSServiceDao($dbStub);
    }

    // ─── getTranslationsForTMXExport ───

    #[Test]
    public function test_getTranslationsForTMXExport_returns_rows(): void
    {
        $rows = [
            [
                'id_segment'       => 1,
                'id_job'           => 100,
                'filename'         => 'test.xliff',
                'segment'          => 'Hello',
                'translation'      => 'Ciao',
                'translation_date' => '2026-01-01 00:00:00',
                'status'           => TranslationStatus::STATUS_TRANSLATED,
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getTranslationsForTMXExport(100, 'abc123');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Hello', $result[0]['segment']);
        $this->assertSame('Ciao', $result[0]['translation']);
        $this->assertSame(100, $result[0]['id_job']);
    }

    #[Test]
    public function test_getTranslationsForTMXExport_returns_empty_array_when_no_matches(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn([]);

        $result = $dao->getTranslationsForTMXExport(999, 'no-match');

        $this->assertSame([], $result);
    }

    // ─── getMTForTMXExport ───

    #[Test]
    public function test_getMTForTMXExport_returns_MT_rows(): void
    {
        $rows = [
            [
                'id_segment'       => 2,
                'id_job'           => 100,
                'filename'         => '',
                'segment'          => 'Hello',
                'translation'      => 'Ciao MT',
                'translation_date' => '2026-01-01 00:00:00',
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getMTForTMXExport(100, 'abc123');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Ciao MT', $result[0]['translation']);
    }

    #[Test]
    public function test_getMTForTMXExport_returns_empty_array_when_no_MT(): void
    {
        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn([]);

        $result = $dao->getMTForTMXExport(100, 'abc123');

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_getMTForTMXExport_throws_RuntimeException_on_PDO_failure(): void
    {
        $this->expectException(RuntimeException::class);

        $dao = $this->createFailingDao();
        $dao->getMTForTMXExport(100, 'abc123');
    }

    // ─── getTMForTMXExport ───

    #[Test]
    public function test_getTMForTMXExport_keeps_translated_rows_unchanged(): void
    {
        $rows = [
            [
                'id_segment'        => 3,
                'id_job'            => 100,
                'filename'          => '',
                'segment'           => 'Hello',
                'translation'       => 'Ciao TM',
                'translation_date'  => '2026-01-01 00:00:00',
                'status'            => TranslationStatus::STATUS_TRANSLATED,
                'suggestions_array' => '[]',
                'tm_keys'           => '[]',
                'id_customer'       => 'test@test.com',
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getTMForTMXExport(100, 'abc123');

        $this->assertCount(1, $result);
        $this->assertSame('Hello', $result[0]['segment']);
        $this->assertSame('Ciao TM', $result[0]['translation']);
    }

    #[Test]
    public function test_getTMForTMXExport_keeps_approved_rows_unchanged(): void
    {
        $rows = [
            [
                'id_segment'        => 3,
                'id_job'            => 100,
                'filename'          => '',
                'segment'           => 'Approved source',
                'translation'       => 'Approved target',
                'translation_date'  => '2026-01-01 00:00:00',
                'status'            => TranslationStatus::STATUS_APPROVED,
                'suggestions_array' => '[]',
                'tm_keys'           => '[]',
                'id_customer'       => 'test@test.com',
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getTMForTMXExport(100, 'abc123');

        $this->assertCount(1, $result);
        $this->assertSame('Approved source', $result[0]['segment']);
        $this->assertSame('Approved target', $result[0]['translation']);
    }

    #[Test]
    public function test_getTMForTMXExport_replaces_draft_row_with_first_TM_suggestion(): void
    {
        $suggestions = json_encode([
            (object)[
                'segment'    => 'TM Source',
                'translation' => 'TM Target',
                'created_by' => 'TM_USER',
                'match'      => '85%',
                'memory_key' => 'abc123',
            ],
        ]);

        $rows = [
            [
                'id_segment'        => 4,
                'id_job'            => 100,
                'filename'          => '',
                'segment'           => 'Original',
                'translation'       => 'Draft',
                'translation_date'  => '2026-01-01 00:00:00',
                'status'            => 'DRAFT',
                'suggestions_array' => $suggestions,
                'tm_keys'           => '[]',
                'id_customer'       => 'test@test.com',
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getTMForTMXExport(100, 'abc123');

        $this->assertCount(1, $result);
        $this->assertSame('TM Source', $result[0]['segment']);
        $this->assertSame('TM Target', $result[0]['translation']);
        $this->assertSame('MateCat_OmegaT_Export', $result[0]['_created_by']);
    }

    #[Test]
    public function test_getTMForTMXExport_skips_MT_suggestions_and_uses_first_TM(): void
    {
        $suggestions = json_encode([
            (object)[
                'segment'    => 'MT Source',
                'translation' => 'MT Target',
                'created_by' => 'MT',
                'match'      => '90%',
                'memory_key' => 'key1',
            ],
            (object)[
                'segment'    => 'TM Source',
                'translation' => 'TM Target',
                'created_by' => 'TM_USER',
                'match'      => '80%',
                'memory_key' => 'key2',
            ],
        ]);

        $rows = [
            [
                'id_segment'        => 5,
                'id_job'            => 100,
                'filename'          => '',
                'segment'           => 'Original',
                'translation'       => 'Draft',
                'translation_date'  => '2026-01-01 00:00:00',
                'status'            => 'NEW',
                'suggestions_array' => $suggestions,
                'tm_keys'           => '[]',
                'id_customer'       => 'test@test.com',
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getTMForTMXExport(100, 'abc123');

        $this->assertCount(1, $result);
        $this->assertSame('TM Source', $result[0]['segment']);
        $this->assertSame('TM Target', $result[0]['translation']);
    }

    #[Test]
    public function test_getTMForTMXExport_removes_rows_with_only_MT_suggestions(): void
    {
        $suggestions = json_encode([
            (object)[
                'segment'    => 'MT Source',
                'translation' => 'MT Target',
                'created_by' => 'MT',
                'match'      => '90%',
                'memory_key' => 'key1',
            ],
        ]);

        $rows = [
            [
                'id_segment'        => 6,
                'id_job'            => 100,
                'filename'          => '',
                'segment'           => 'Original',
                'translation'       => 'Draft',
                'translation_date'  => '2026-01-01 00:00:00',
                'status'            => 'NEW',
                'suggestions_array' => $suggestions,
                'tm_keys'           => '[]',
                'id_customer'       => 'test@test.com',
            ],
        ];

        [$dao, $stmtStub] = $this->createMockedDao();
        $stmtStub->method('fetchAll')->willReturn($rows);

        $result = $dao->getTMForTMXExport(100, 'abc123');

        $this->assertSame([], $result);
    }

    #[Test]
    public function test_getTMForTMXExport_throws_RuntimeException_on_PDO_failure(): void
    {
        $this->expectException(RuntimeException::class);

        $dao = $this->createFailingDao();
        $dao->getTMForTMXExport(100, 'abc123');
    }
}
