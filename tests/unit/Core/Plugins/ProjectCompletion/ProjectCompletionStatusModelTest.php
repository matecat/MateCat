<?php

namespace Matecat\Core\Plugins\ProjectCompletion;

use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ProjectCompletion\Model\ProjectCompletionStatusModel;

class ProjectCompletionStatusModelTest extends AbstractTest
{
    private function makeChunk(int $id = 1, string $password = 'pwd'): JobStruct
    {
        return new JobStruct([
            'id' => $id,
            'id_project' => 100,
            'password' => $password,
            'job_first_segment' => 1,
            'job_last_segment' => 10,
            'source' => 'en-US',
            'target' => 'it-IT',
            'create_date' => '2026-01-01 00:00:00',
            'last_update' => '2026-01-01 00:00:00',
        ]);
    }

    private function makeProject(array $chunks): ProjectStruct
    {
        $project = $this->createStub(ProjectStruct::class);
        $project->id = 100;
        $project->method('getChunks')->willReturn($chunks);

        return $project;
    }

    private function makeCompletionRecord(int $eventId, string $createDate): array
    {
        return [
            'id_event' => $eventId,
            'create_date' => $createDate,
        ];
    }

    #[Test]
    public function getStatusAllCompleted(): void
    {
        $chunk = $this->makeChunk();
        $project = $this->makeProject([$chunk]);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('lastCompletionRecord')->willReturn(
            $this->makeCompletionRecord(1, '2026-01-01 12:00:00')
        );

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new ProjectCompletionStatusModel($project, $featureSet, $eventDao);
        $status = $model->getStatus();

        $this->assertTrue($status['completed']);
        $this->assertSame(100, $status['id']);
        $this->assertCount(1, $status['translate']);
        $this->assertCount(1, $status['revise']);
        $this->assertTrue($status['translate'][0]['completed']);
        $this->assertTrue($status['revise'][0]['completed']);
    }

    #[Test]
    public function getStatusNoneCompleted(): void
    {
        $chunk = $this->makeChunk();
        $project = $this->makeProject([$chunk]);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('lastCompletionRecord')->willReturn([]);

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new ProjectCompletionStatusModel($project, $featureSet, $eventDao);
        $status = $model->getStatus();

        $this->assertFalse($status['completed']);
        $this->assertFalse($status['translate'][0]['completed']);
        $this->assertNull($status['translate'][0]['completed_at']);
        $this->assertNull($status['translate'][0]['event_id']);
    }

    #[Test]
    public function getStatusTranslateCompletedReviseNot(): void
    {
        $chunk = $this->makeChunk();
        $project = $this->makeProject([$chunk]);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('lastCompletionRecord')->willReturnCallback(
            function (JobStruct $c, array $params) {
                if ($params['is_review'] === false) {
                    return $this->makeCompletionRecord(1, '2026-01-01 12:00:00');
                }

                return [];
            }
        );

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new ProjectCompletionStatusModel($project, $featureSet, $eventDao);
        $status = $model->getStatus();

        $this->assertFalse($status['completed']);
        $this->assertTrue($status['translate'][0]['completed']);
        $this->assertFalse($status['revise'][0]['completed']);
    }

    #[Test]
    public function getStatusCachesResult(): void
    {
        $chunk = $this->makeChunk();
        $project = $this->makeProject([$chunk]);

        $eventDao = $this->createMock(ChunkCompletionEventDao::class);
        $eventDao->expects($this->exactly(2))
            ->method('lastCompletionRecord')
            ->willReturn([]);

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new ProjectCompletionStatusModel($project, $featureSet, $eventDao);
        $first = $model->getStatus();
        $second = $model->getStatus();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function getStatusMultipleChunks(): void
    {
        $chunk1 = $this->makeChunk(1, 'pw1');
        $chunk2 = $this->makeChunk(2, 'pw2');
        $project = $this->makeProject([$chunk1, $chunk2]);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('lastCompletionRecord')->willReturn(
            $this->makeCompletionRecord(1, '2026-01-01 12:00:00')
        );

        $featureSet = $this->createStub(FeatureSet::class);

        $model = new ProjectCompletionStatusModel($project, $featureSet, $eventDao);
        $status = $model->getStatus();

        $this->assertTrue($status['completed']);
        $this->assertCount(2, $status['translate']);
        $this->assertCount(2, $status['revise']);
    }

    #[Test]
    public function getStatusDispatchesFeatureSetForReviewPassword(): void
    {
        $chunk = $this->makeChunk();
        $project = $this->makeProject([$chunk]);

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('lastCompletionRecord')->willReturn([]);

        $featureSet = $this->createMock(FeatureSet::class);
        $featureSet->expects($this->once())->method('loadForProject');
        $featureSet->expects($this->once())->method('dispatch');

        $model = new ProjectCompletionStatusModel($project, $featureSet, $eventDao);
        $model->getStatus();
    }
}
