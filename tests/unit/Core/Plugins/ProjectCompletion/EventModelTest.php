<?php

namespace Matecat\Core\Plugins\ProjectCompletion;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ProjectCompletion\Model\EventModel;

class EventModelTest extends AbstractTest
{
    private function makeDatabase(): IDatabase
    {
        [$dbStub] = $this->createDatabaseMock();

        return $dbStub;
    }

    private function makeChunk(int $idProject = 100): JobStruct
    {
        return new JobStruct([
            'id' => 1,
            'id_project' => $idProject,
            'password' => 'pwd',
            'job_first_segment' => 1,
            'job_last_segment' => 10,
            'source' => 'en-US',
            'target' => 'it-IT',
            'create_date' => '2026-01-01 00:00:00',
            'last_update' => '2026-01-01 00:00:00',
        ]);
    }

    private function makeEventStruct(bool $isReview = false): CompletionEventStruct
    {
        return new CompletionEventStruct([
            'uid' => 1,
            'remote_ip_address' => '127.0.0.1',
            'source' => 'user',
            'is_review' => $isReview,
        ]);
    }

    #[Test]
    public function saveCreatesEventAndDispatches(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(false);
        $project = new ProjectStruct(['id' => 100]);

        $eventDao = $this->createMock(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);
        $eventDao->expects($this->once())
            ->method('createFromChunk')
            ->with($chunk, $struct)
            ->willReturn('42');

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);

        $featureSet = $this->createMock(FeatureSet::class);
        $featureSet->expects($this->once())->method('loadForProject')->with($project);
        $featureSet->expects($this->once())->method('dispatch');

        $model = new EventModel($chunk, $struct, $eventDao, $projectDao, $featureSet, $this->makeDatabase());
        $model->save();

        $this->assertSame(42, $model->getChunkCompletionEventId());
    }

    #[Test]
    public function saveThrowsWhenProjectNotFound(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(false);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);
        $eventDao->method('createFromChunk')->willReturn('1');

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn(null);

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new EventModel($chunk, $struct, $eventDao, $projectDao, $featureSet, $this->makeDatabase());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Project not found for chunk 100');

        $model->save();
    }

    #[Test]
    public function saveThrowsOnPhaseMismatchReviewDuringTranslate(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(true);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);

        $model = new EventModel($chunk, $struct, $eventDao, $this->createStub(ProjectDao::class), new FeatureSet($this->createStub(IDatabase::class)), $this->makeDatabase());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot save event, current status mismatch.');

        $model->save();
    }

    #[Test]
    public function saveThrowsOnPhaseMismatchTranslateDuringReview(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(false);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::REVISE);

        $model = new EventModel($chunk, $struct, $eventDao, $this->createStub(ProjectDao::class), new FeatureSet($this->createStub(IDatabase::class)), $this->makeDatabase());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot save event, current status mismatch.');

        $model->save();
    }

    #[Test]
    public function savePassesOnReviewPhaseMatch(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(true);
        $project = new ProjectStruct(['id' => 100]);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::REVISE);
        $eventDao->method('createFromChunk')->willReturn('5');

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new EventModel($chunk, $struct, $eventDao, $projectDao, $featureSet, $this->makeDatabase());
        $model->save();

        $this->assertSame(5, $model->getChunkCompletionEventId());
    }

    /**
     * The event row and the dispatch have to be one unit. Beyond atomicity of the undo_data
     * snapshot, the dispatch reaches QualityReportModel::resetScore(), which takes the job's
     * qa_chunk_reviews row locks — and lockByJobId() refuses to run outside a transaction, so
     * without this wrap POST /api/app/set-chunk-completed returned 500 for every
     * revision-enabled project.
     */
    #[Test]
    public function saveWrapsTheEventInsertAndTheDispatchInOneTransaction(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(false);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);
        $eventDao->method('createFromChunk')->willReturn('42');

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn(new ProjectStruct(['id' => 100]));

        // The scope owns the transaction. Reaching past it is not expressible here any more —
        // begin() and commit() are no longer on IDatabase — so the scope is what is left to assert.
        $database = $this->createMock(IDatabase::class);
        $database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(static fn(callable $work) => $work());

        (new EventModel($chunk, $struct, $eventDao, $projectDao, $this->createStub(FeatureSet::class), $database))->save();
    }

    #[Test]
    public function saveLetsAFailedDispatchUnwindTheTransactionScope(): void
    {
        $chunk = $this->makeChunk();
        $struct = $this->makeEventStruct(false);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);
        $eventDao->method('createFromChunk')->willReturn('42');

        // findById returning null makes save() throw after the insert, i.e. mid-unit.
        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn(null);

        // The scope aborts the transaction and re-throws; the failure has to reach the caller rather
        // than be swallowed by whatever closed the transaction.
        $database = $this->createMock(IDatabase::class);
        $database->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(static fn(callable $work) => $work());

        $model = new EventModel($chunk, $struct, $eventDao, $projectDao, $this->createStub(FeatureSet::class), $database);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Project not found for chunk 100');
        $model->save();
    }

    #[Test]
    public function getChunkCompletionEventIdReturnsNullBeforeSave(): void
    {
        $model = new EventModel(
            $this->makeChunk(),
            $this->makeEventStruct(),
            $this->createStub(ChunkCompletionEventDao::class),
            $this->createStub(ProjectDao::class),
            new FeatureSet($this->createStub(IDatabase::class)),
            $this->makeDatabase(),
        );

        $this->assertNull($model->getChunkCompletionEventId());
    }
}
