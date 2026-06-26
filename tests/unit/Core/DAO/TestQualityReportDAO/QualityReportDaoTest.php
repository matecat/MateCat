<?php

namespace Matecat\Core\DAO\TestQualityReportDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\QualityReport\QualityReportDao;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class QualityReportDaoTest extends AbstractTest
{
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [,, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function makeChunk(int $id = 10, string $password = 'pass1'): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = $id;
        $chunk->password = $password;

        return $chunk;
    }

    #[Test]
    public function getSegmentsForQualityReportReturnsArray(): void
    {
        $row = ['segment_id' => 1, 'file_id' => 1, 'translation' => 'test'];
        $this->stmtStub->method('fetchAll')->willReturn([$row]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getSegmentsForQualityReport($this->makeChunk());

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getSegmentsForQualityReportReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getSegmentsForQualityReport($this->makeChunk());

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function getIssuesBySegmentsReturnsArray(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->segment_id = 100;
        $struct->issue_id = 1;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getIssuesBySegments([100, 101], 10);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $results[0]);
    }

    #[Test]
    public function getIssuesBySegmentsReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getIssuesBySegments([100], 10);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function getIssuesBySegmentsWithSingleSegment(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->segment_id = 50;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getIssuesBySegments([50], 5);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getAveragesReturnsAssocArray(): void
    {
        $row = ['avg_time_to_edit' => 5000, 'avg_edit_distance' => 15];
        $this->stmtStub->method('fetch')->willReturn($row);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getAverages($this->makeChunk());

        $this->assertIsArray($result);
        $this->assertSame(5000, $result['avg_time_to_edit']);
        $this->assertSame(15, $result['avg_edit_distance']);
    }

    #[Test]
    public function getAveragesReturnsFalseWhenNoData(): void
    {
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $result = $dao->getAverages($this->makeChunk());

        $this->assertFalse($result);
    }

    #[Test]
    public function getReviseIssuesByChunkReturnsArray(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->issue_id = 1;
        $struct->issue_severity = 'major';

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getReviseIssuesByChunk(10, 'pass1');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $results[0]);
    }

    #[Test]
    public function getReviseIssuesByChunkReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getReviseIssuesByChunk(10, 'pass1');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function getReviseIssuesByChunkWithExplicitSourcePage(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->issue_id = 2;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);

        $dao = new QualityReportDao(\Model\DataAccess\Database::obtain());
        $results = $dao->getReviseIssuesByChunk(10, 'pass1', 3);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }
}
