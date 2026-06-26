<?php

namespace Matecat\Core\Model\Translations;

use Matecat\TestHelpers\AbstractTest;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class SegmentTranslationDaoTest extends AbstractTest
{
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    // ─── getByJobId() ───

    #[Test]
    public function getByJobIdReturnsStructs(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->id_segment = 1;
        $struct->id_job = 5;
        $struct->status = 'TRANSLATED';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getByJobId(5);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getByJobIdReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getByJobId(999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── findBySegmentAndJob() ───

    #[Test]
    public function findBySegmentAndJobReturnsStruct(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->id_segment = 10;
        $struct->id_job = 5;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $result = $dao->findBySegmentAndJob(10, 5);

        $this->assertInstanceOf(SegmentTranslationStruct::class, $result);
    }

    #[Test]
    public function findBySegmentAndJobReturnsNull(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $result = $dao->findBySegmentAndJob(999, 999);

        $this->assertNull($result);
    }

    // ─── updateSuggestionsArray() ───

    #[Test]
    public function updateSuggestionsArraySkipsEmpty(): void
    {
        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $dao->updateSuggestionsArray(1, []);

        $this->assertTrue(true);
    }

    #[Test]
    public function updateSuggestionsArrayExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $dao->updateSuggestionsArray(1, [['suggestion' => 'test', 'match' => '100%']]);

        $this->assertTrue(true);
    }

    // ─── getByFile() ───

    #[Test]
    public function getByFileReturnsStructs(): void
    {
        $file = new \Model\Files\FileStruct();
        $file->id = 1;

        $struct = new SegmentTranslationStruct();
        $struct->id_segment = 10;
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getByFile($file);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    // ─── getAllSegmentsByIdListAndJobId() ───

    #[Test]
    public function getAllSegmentsByIdListAndJobIdReturnsStructs(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->id_segment = 1;
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getAllSegmentsByIdListAndJobId([1, 2, 3], 5);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    #[Test]
    public function getAllSegmentsByIdListAndJobIdReturnsEmptyForEmptyList(): void
    {
        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getAllSegmentsByIdListAndJobId([], 5);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ─── updateLastTranslationDateByIdList() ───

    #[Test]
    public function updateLastTranslationDateByIdListSkipsEmptyList(): void
    {
        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $dao->updateLastTranslationDateByIdList([], '2026-01-01 00:00:00');

        $this->assertTrue(true);
    }

    #[Test]
    public function updateLastTranslationDateByIdListExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $dao->updateLastTranslationDateByIdList([1, 2], '2026-01-01 00:00:00');

        $this->assertTrue(true);
    }

    // ─── getMaxSegmentIdsFromJob() ───

    #[Test]
    public function getMaxSegmentIdsFromJobReturnsIds(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([[100], [200]]);

        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 5;

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getMaxSegmentIdsFromJob($chunk);

        $this->assertSame([100, 200], $results);
    }

    #[Test]
    public function getMaxSegmentIdsFromJobReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 5;

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getMaxSegmentIdsFromJob($chunk);

        $this->assertEmpty($results);
    }

    // ─── getWordsPerSecond() ───

    #[Test]
    public function getWordsPerSecondReturnsResults(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([['words_per_second' => 5]]);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getWordsPerSecond(1, [10, 20]);

        $this->assertCount(1, $results);
        $this->assertSame(5, $results[0]['words_per_second']);
    }

    // ─── setAnalysisValue() ───

    #[Test]
    public function setAnalysisValueReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new SegmentTranslationDao(\Model\DataAccess\Database::obtain());
        $result = $dao->setAnalysisValue([
            'id_segment' => 10,
            'id_job' => 5,
            'eq_word_count' => 100.5,
        ]);

        $this->assertSame(1, $result);
    }
}
