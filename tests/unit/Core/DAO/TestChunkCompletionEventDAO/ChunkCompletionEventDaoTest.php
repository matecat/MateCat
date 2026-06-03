<?php

namespace Matecat\Core\DAO\TestChunkCompletionEventDAO;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class ChunkCompletionEventDaoTest extends AbstractTest
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

    private function makeChunk(): JobStruct
    {
        $project = new ProjectStruct();
        $project->id = 1;

        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 10;
        $chunk->password = 'pass1';
        $chunk->job_first_segment = 100;
        $chunk->job_last_segment = 200;
        $chunk->method('getProject')->willReturn($project);

        return $chunk;
    }

    #[Test]
    public function validSourcesReturnsExpectedKeys(): void
    {
        $dao = new ChunkCompletionEventDao();
        $sources = $dao->validSources();

        $this->assertIsArray($sources);
        $this->assertArrayHasKey('user', $sources);
        $this->assertArrayHasKey('merge', $sources);
        $this->assertSame(ChunkCompletionEventStruct::SOURCE_USER, $sources['user']);
        $this->assertSame(ChunkCompletionEventStruct::SOURCE_MERGE, $sources['merge']);
    }

    #[Test]
    public function createFromChunkReturnsInsertId(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $pdo = $this->createDatabaseMock()[1];
        $pdo->method('lastInsertId')->willReturn('42');

        $params = new CompletionEventStruct();
        $params->source = 'user';
        $params->remote_ip_address = '127.0.0.1';
        $params->uid = 1;
        $params->is_review = false;

        $dao = new ChunkCompletionEventDao();
        $result = $dao->createFromChunk($this->makeChunk(), $params);

        $this->assertSame('42', $result);
    }

    #[Test]
    public function lastCompletionRecordReturnsArrayWhenFound(): void
    {
        $row = ['id_event' => 1, 'id_job' => 10, 'password' => 'pass1', 'is_review' => false, 'create_date' => '2024-01-01'];
        $this->stmtStub->method('fetch')->willReturn($row);

        $dao = new ChunkCompletionEventDao();
        $result = $dao->lastCompletionRecord($this->makeChunk(), ['is_review' => false]);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id_event']);
    }

    #[Test]
    public function lastCompletionRecordReturnsEmptyArrayWhenNotFound(): void
    {
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new ChunkCompletionEventDao();
        $result = $dao->lastCompletionRecord($this->makeChunk(), ['is_review' => true]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function deleteEventReturnsRowCount(): void
    {
        $this->stmtStub->method('rowCount')->willReturn(1);

        $event = new ChunkCompletionEventStruct();
        $event->id = 5;

        $dao = new ChunkCompletionEventDao();
        $result = $dao->deleteEvent($event);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function getByIdAndChunkReturnsStructWhenFound(): void
    {
        $event = new ChunkCompletionEventStruct();
        $event->id = 1;
        $this->stmtStub->method('fetch')->willReturn($event);

        $dao = new ChunkCompletionEventDao();
        $result = $dao->getByIdAndChunk(1, $this->makeChunk());

        $this->assertInstanceOf(ChunkCompletionEventStruct::class, $result);
    }

    #[Test]
    public function getByIdAndChunkReturnsFalseWhenNotFound(): void
    {
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new ChunkCompletionEventDao();
        $result = $dao->getByIdAndChunk(999, $this->makeChunk());

        $this->assertFalse($result);
    }

    #[Test]
    public function updatePasswordReturnsRowCount(): void
    {
        $this->stmtStub->method('rowCount')->willReturn(2);

        $dao = new ChunkCompletionEventDao();
        $result = $dao->updatePassword(10, 'new_pass', 'old_pass');

        $this->assertSame(2, $result);
    }
}
