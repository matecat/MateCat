<?php

namespace Matecat\Core\DAO\TestAnalysisDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\AnalysisDao;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class AnalysisDaoTest extends AbstractTest
{
    private IDatabase $dbStub;
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    #[Test]
    public function destroyAnalysisProjectCacheReturnsBool(): void
    {
        $dao = new AnalysisDao(obtainTestDatabase());
        $result = $dao->destroyAnalysisProjectCache(42);

        $this->assertIsBool($result);
    }

    #[Test]
    public function destroyAnalysisProjectCacheWithDifferentIds(): void
    {
        $dao = new AnalysisDao(obtainTestDatabase());

        $result1 = $dao->destroyAnalysisProjectCache(1);
        $result2 = $dao->destroyAnalysisProjectCache(500);

        $this->assertIsBool($result1);
        $this->assertIsBool($result2);
    }

    // ── Instance method tests (specular) ──

    #[Test]
    public function instanceGetProjectStatsVolumeAnalysisReturnsResults(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->jid = 1;
        $struct->sid = 100;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $dao = new AnalysisDao(obtainTestDatabase());
        $results = $dao->getProjectStatsVolumeAnalysis(42);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $results[0]);
    }

    #[Test]
    public function instanceGetProjectStatsVolumeAnalysisReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $dao = new AnalysisDao(obtainTestDatabase());
        $results = $dao->getProjectStatsVolumeAnalysis(99);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function instanceGetProjectStatsVolumeAnalysisFiltersNonMatchingInstances(): void
    {
        $validStruct = new ShapelessConcreteStruct();
        $validStruct->jid = 1;
        $invalidObject = new \stdClass();

        $this->stmtStub->method('fetchAll')->willReturn([$validStruct, $invalidObject]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $dao = new AnalysisDao(obtainTestDatabase());
        $results = $dao->getProjectStatsVolumeAnalysis(10);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function instanceGetProjectStatsVolumeAnalysisWithTtl(): void
    {
        $struct = new ShapelessConcreteStruct();
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $dao = new AnalysisDao(obtainTestDatabase());
        $results = $dao->getProjectStatsVolumeAnalysis(42, 3600);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function instanceDestroyCacheByProjectIdReturnsBool(): void
    {
        $dao = new AnalysisDao(obtainTestDatabase());
        $result = $dao->destroyCacheByProjectId(42);

        $this->assertIsBool($result);
    }

    #[Test]
    public function instanceDestroyCacheByProjectIdWithDifferentIds(): void
    {
        $dao = new AnalysisDao(obtainTestDatabase());

        $result1 = $dao->destroyCacheByProjectId(1);
        $result2 = $dao->destroyCacheByProjectId(999);

        $this->assertIsBool($result1);
        $this->assertIsBool($result2);
    }
}
