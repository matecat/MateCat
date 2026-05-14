<?php

namespace unit\DAO\TestAnalysisDAO;

use Model\Analysis\AnalysisDao;
use Utils\Registry\AppConfig;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AnalysisDaoTest extends TestCase
{
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        AppConfig::$SKIP_SQL_CACHE = true;

        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';

        $this->pdoStub = $this->createStub(PDO::class);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $dbStub = $this->createStub(Database::class);
        $dbStub->method('getConnection')->willReturn($this->pdoStub);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $dbStub);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        AppConfig::$SKIP_SQL_CACHE = false;
    }

    #[Test]
    public function getProjectStatsVolumeAnalysisReturnsResults(): void
    {
        $struct = new ShapelessConcreteStruct();
        $struct->jid = 1;
        $struct->sid = 100;

        $this->stmtStub->method('fetchAll')->willReturn([$struct]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $results = AnalysisDao::getProjectStatsVolumeAnalysis(42);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(ShapelessConcreteStruct::class, $results[0]);
    }

    #[Test]
    public function getProjectStatsVolumeAnalysisReturnsEmptyArray(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $results = AnalysisDao::getProjectStatsVolumeAnalysis(99);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    #[Test]
    public function getProjectStatsVolumeAnalysisFiltersNonMatchingInstances(): void
    {
        $validStruct = new ShapelessConcreteStruct();
        $validStruct->jid = 1;
        $invalidObject = new \stdClass();

        $this->stmtStub->method('fetchAll')->willReturn([$validStruct, $invalidObject]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $results = AnalysisDao::getProjectStatsVolumeAnalysis(10);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getProjectStatsVolumeAnalysisWithTtl(): void
    {
        $struct = new ShapelessConcreteStruct();
        $this->stmtStub->method('fetchAll')->willReturn([$struct]);
        $this->stmtStub->method('closeCursor')->willReturn(true);

        $results = AnalysisDao::getProjectStatsVolumeAnalysis(42, 3600);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function destroyCacheByProjectIdReturnsBool(): void
    {
        $result = AnalysisDao::destroyCacheByProjectId(42);

        $this->assertIsBool($result);
    }

    #[Test]
    public function destroyAnalysisProjectCacheReturnsBool(): void
    {
        $dao = new AnalysisDao();
        $result = $dao->destroyAnalysisProjectCache(42);

        $this->assertIsBool($result);
    }

    #[Test]
    public function destroyCacheByProjectIdWithDifferentIds(): void
    {
        $result1 = AnalysisDao::destroyCacheByProjectId(1);
        $result2 = AnalysisDao::destroyCacheByProjectId(999);

        $this->assertIsBool($result1);
        $this->assertIsBool($result2);
    }

    #[Test]
    public function destroyAnalysisProjectCacheWithDifferentIds(): void
    {
        $dao = new AnalysisDao();

        $result1 = $dao->destroyAnalysisProjectCache(1);
        $result2 = $dao->destroyAnalysisProjectCache(500);

        $this->assertIsBool($result1);
        $this->assertIsBool($result2);
    }
}
