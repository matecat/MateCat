<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Utils\Registry\AppConfig;
use View\API\V2\Json\Chunk;

#[CoversClass(Chunk::class)]
class ChunkTest extends AbstractTest
{
    private IDatabase&Stub $dbStub;
    private PDO&Stub $pdoStub;
    private PDOStatement&Stub $stmtStub;
    private static bool $originalSkipCache;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
        $this->stmtStub->method('fetchAll')->willReturn([]);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = self::$originalSkipCache;
        parent::tearDown();
    }

    public function testInstantiationSucceeds(): void
    {
        $view = new Chunk($this->dbStub);
        $this->assertInstanceOf(Chunk::class, $view);
    }

    public function testRenderOneReturnsJobWrapper(): void
    {
        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 99;

        $project = $this->createStub(ProjectStruct::class);
        $project->id = 1;

        $chunk->method('getProject')->willReturn($project);

        // Subclass overrides renderItem to avoid ChunkReviewDao DB calls
        $view = new class($this->dbStub) extends Chunk {
            public function renderItem(JobStruct $chunk, ProjectStruct $project, FeatureSet $featureSet): array
            {
                return ['id' => $chunk->id, 'stub' => true];
            }
        };

        $result = $view->renderOne($chunk);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('job', $result);
        $this->assertSame(99, $result['job']['id']);
        $this->assertArrayHasKey('chunks', $result['job']);
        $this->assertCount(1, $result['job']['chunks']);
    }
}
