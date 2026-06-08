<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\Chunk;

#[CoversClass(Chunk::class)]
class ChunkTest extends AbstractTest
{
    public function testInstantiationSucceeds(): void
    {
        $view = new Chunk();
        $this->assertInstanceOf(Chunk::class, $view);
    }

    public function testRenderOneReturnsJobWrapper(): void
    {
        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 99;

        $featureSet = $this->createStub(FeatureSet::class);
        $project    = $this->createStub(ProjectStruct::class);
        $project->method('getFeaturesSet')->willReturn($featureSet);

        $chunk->method('getProject')->willReturn($project);

        // Subclass overrides renderItem to avoid ChunkReviewDao DB calls
        $view = new class extends Chunk {
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
