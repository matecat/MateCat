<?php

namespace Tests\unit\View\API\V3\Json;

use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use View\API\V3\Json\Chunk;

#[CoversClass(Chunk::class)]
class ChunkTest extends TestCase
{
    private function invokePopulateRevisePasswords(ChunkReviewStruct $review, array $result): array
    {
        $method = new ReflectionMethod(Chunk::class, 'populateRevisePasswords');

        return $method->invoke(null, $review, $result);
    }

    private function makeTteStruct(int $tte): ShapelessConcreteStruct
    {
        $struct = new ShapelessConcreteStruct();
        $struct->tte = $tte;

        return $struct;
    }

    private function createMockJobStruct(): JobStruct
    {
        $chunk = $this->createStub(JobStruct::class);

        $chunk->id = 100;
        $chunk->password = 'test_pass';
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';
        $chunk->status_owner = 'active';
        $chunk->subject = 'general';
        $chunk->owner = 'owner@test.com';
        $chunk->total_time_to_edit = 5000;
        $chunk->avg_post_editing_effort = 75;
        $chunk->create_date = '2024-01-15 10:00:00';
        $chunk->total_raw_wc = 1200;
        $chunk->standard_analysis_wc = 1000;
        $chunk->new_words = 100.0;
        $chunk->draft_words = 50.0;
        $chunk->translated_words = 800.0;
        $chunk->approved_words = 200.0;
        $chunk->approved2_words = 0.0;
        $chunk->rejected_words = 50.0;
        $chunk->new_raw_words = 110;
        $chunk->draft_raw_words = 55;
        $chunk->translated_raw_words = 880;
        $chunk->approved_raw_words = 220;
        $chunk->approved2_raw_words = 0;
        $chunk->rejected_raw_words = 55;

        $chunk->method('getOutsource')->willReturn(null);
        $chunk->method('getTranslator')->willReturn(null);
        $chunk->method('getOpenThreadsCount')->willReturn(3);
        $chunk->method('getPeeForTranslatedSegments')->willReturn(42.5);
        $chunk->method('getWarningsCount')->willReturn((object) [
            'warnings_count'   => 2,
            'warning_segments' => [101, 102],
        ]);

        return $chunk;
    }

    private function createTestableChunk(JobDao $jobDao, ChunkReviewDao $chunkReviewDao): Chunk
    {
        return new class ($jobDao, $chunkReviewDao) extends Chunk {
            protected function fillUrls(array $result, JobStruct $chunk, ProjectStruct $project, FeatureSet $featureSet): array
            {
                $result['urls'] = [];
                return $result;
            }

            protected function getKeyList(JobStruct $jStruct): array
            {
                return [];
            }

            protected function renderQualitySummary(JobStruct $chunk, ProjectStruct $project, array $chunkReviewsList): array
            {
                return ['quality_summary' => []];
            }
        };
    }

    // ─── populateRevisePasswords ──────────────────────────────────────────────────

    #[Test]
    public function populateRevisePasswordsAddsRevision1ForSourcePageRevision(): void
    {
        $review                  = new ChunkReviewStruct();
        $review->source_page     = 2;
        $review->review_password = 'abc123';

        $result = $this->invokePopulateRevisePasswords($review, []);

        $this->assertArrayHasKey('revise_passwords', $result);
        $this->assertCount(1, $result['revise_passwords']);
        $this->assertSame(1, $result['revise_passwords'][0]['revision_number']);
        $this->assertSame('abc123', $result['revise_passwords'][0]['password']);
    }

    #[Test]
    public function populateRevisePasswordsAddsHigherRevisionNumbers(): void
    {
        $review                  = new ChunkReviewStruct();
        $review->source_page     = 3;
        $review->review_password = 'xyz789';

        $result = $this->invokePopulateRevisePasswords($review, []);

        $this->assertArrayHasKey('revise_passwords', $result);
        $this->assertSame(2, $result['revise_passwords'][0]['revision_number']);
        $this->assertSame('xyz789', $result['revise_passwords'][0]['password']);
    }

    #[Test]
    public function populateRevisePasswordsAppendsToExisting(): void
    {
        $existingResult = ['revise_passwords' => [['revision_number' => 1, 'password' => 'existing']]];

        $review                  = new ChunkReviewStruct();
        $review->source_page     = 3;
        $review->review_password = 'new_pass';

        $result = $this->invokePopulateRevisePasswords($review, $existingResult);

        $this->assertCount(2, $result['revise_passwords']);
    }

    // ─── setChunkReviews / getChunkReviews ───────────────────────────────────────

    #[Test]
    public function setChunkReviewsBypassesDaoLookup(): void
    {
        $chunkReviewDao = $this->createMock(ChunkReviewDao::class);
        $chunkReviewDao->expects($this->never())->method('findChunkReviews');

        $chunk = new Chunk(chunkReviewDao: $chunkReviewDao);

        $review1             = new ChunkReviewStruct();
        $review1->id         = 1;
        $review1->source_page = 2;

        $chunk->setChunkReviews([$review1]);

        $method = new ReflectionMethod(Chunk::class, 'getChunkReviews');
        $result = $method->invoke($chunk);

        $this->assertCount(1, $result);
        $this->assertSame($review1, $result[0]);
    }

    #[Test]
    public function getChunkReviewsFallsBackToDao(): void
    {
        $jobStruct = new JobStruct();
        $jobStruct->id = 100;
        $jobStruct->password = 'test';

        $review = new ChunkReviewStruct();
        $review->id = 5;
        $review->source_page = 2;

        $chunkReviewDao = $this->createMock(ChunkReviewDao::class);
        $chunkReviewDao->expects($this->once())
            ->method('findChunkReviews')
            ->with($jobStruct)
            ->willReturn([$review]);

        $chunk = new Chunk(chunkReviewDao: $chunkReviewDao);

        $reflProp = new \ReflectionProperty(Chunk::class, 'chunk');
        $reflProp->setValue($chunk, $jobStruct);

        $method = new ReflectionMethod(Chunk::class, 'getChunkReviews');
        $result = $method->invoke($chunk);

        $this->assertCount(1, $result);
        $this->assertSame($review, $result[0]);
    }

    // ─── getTimeToEditArray ──────────────────────────────────────────────────────

    #[Test]
    public function getTimeToEditArrayReturnsSummedValues(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')
            ->willReturnMap([
                [100, 1, $this->makeTteStruct(1000)],
                [100, 2, $this->makeTteStruct(2000)],
                [100, 3, $this->makeTteStruct(500)],
            ]);

        $chunk = new Chunk(jobDao: $jobDao);
        $method = new ReflectionMethod(Chunk::class, 'getTimeToEditArray');
        $result = $method->invoke($chunk, 100);

        $this->assertSame(['total' => 3500, 't' => 1000, 'r1' => 2000, 'r2' => 500], $result);
    }

    #[Test]
    public function getTimeToEditArrayHandlesZeroValues(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')
            ->willReturnMap([
                [100, 1, $this->makeTteStruct(0)],
                [100, 2, $this->makeTteStruct(0)],
                [100, 3, $this->makeTteStruct(0)],
            ]);

        $chunk = new Chunk(jobDao: $jobDao);
        $method = new ReflectionMethod(Chunk::class, 'getTimeToEditArray');
        $result = $method->invoke($chunk, 100);

        $this->assertSame(['total' => 0, 't' => 0, 'r1' => 0, 'r2' => 0], $result);
    }

    // ─── renderItem (integration with mocked dependencies) ───────────────────────

    #[Test]
    public function renderItemBuildsCompleteResultArray(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')
            ->willReturnMap([
                [100, 1, $this->makeTteStruct(1000)],
                [100, 2, $this->makeTteStruct(2000)],
                [100, 3, $this->makeTteStruct(500)],
            ]);

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);

        $chunkView = $this->createTestableChunk($jobDao, $chunkReviewDao);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->review_password = 'rev_pass';
        $chunkView->setChunkReviews([$review]);

        $chunk = $this->createMockJobStruct();
        $project = $this->createStub(ProjectStruct::class);
        $featureSet = $this->createStub(FeatureSet::class);

        $result = $chunkView->renderItem($chunk, $project, $featureSet);

        $this->assertSame(100, $result['id']);
        $this->assertSame('test_pass', $result['password']);
        $this->assertSame('en-US', $result['source']);
        $this->assertSame('it-IT', $result['target']);
        $this->assertSame('English (USA)', $result['sourceTxt']);
        $this->assertSame('Italian (Italy)', $result['targetTxt']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('general', $result['subject']);
        $this->assertSame('General', $result['subject_printable']);
        $this->assertSame('owner@test.com', $result['owner']);
        $this->assertSame(5000, $result['total_time_to_edit']);
        $this->assertSame(75.0, $result['avg_post_editing_effort']);
        $this->assertSame(3, $result['open_threads_count']);
        $this->assertSame(42.5, $result['pee']);
        $this->assertSame(2, $result['warnings_count']);
        $this->assertSame([101, 102], $result['warning_segments']);
        $this->assertNull($result['outsource']);
        $this->assertNull($result['translator']);
        $this->assertSame(1200, $result['total_raw_wc']);
        $this->assertSame(1000.0, $result['standard_wc']);
        $this->assertSame(['total' => 3500, 't' => 1000, 'r1' => 2000, 'r2' => 500], $result['time_to_edit']);
        $this->assertArrayHasKey('urls', $result);
    }

    #[Test]
    public function renderItemPopulatesRevisePasswordsFromReviews(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')->willReturn($this->makeTteStruct(0));

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkView = $this->createTestableChunk($jobDao, $chunkReviewDao);

        $review1 = new ChunkReviewStruct();
        $review1->source_page = 2;
        $review1->review_password = 'pass_r1';

        $review2 = new ChunkReviewStruct();
        $review2->source_page = 3;
        $review2->review_password = 'pass_r2';

        $chunkView->setChunkReviews([$review1, $review2]);

        $chunk = $this->createMockJobStruct();
        $project = $this->createStub(ProjectStruct::class);
        $featureSet = $this->createStub(FeatureSet::class);

        $result = $chunkView->renderItem($chunk, $project, $featureSet);

        $this->assertArrayHasKey('revise_passwords', $result);
        $this->assertCount(2, $result['revise_passwords']);
        $this->assertSame(1, $result['revise_passwords'][0]['revision_number']);
        $this->assertSame('pass_r1', $result['revise_passwords'][0]['password']);
        $this->assertSame(2, $result['revise_passwords'][1]['revision_number']);
        $this->assertSame('pass_r2', $result['revise_passwords'][1]['password']);
    }

    #[Test]
    public function renderItemIncludesQualitySummary(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')->willReturn($this->makeTteStruct(0));

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkView = $this->createTestableChunk($jobDao, $chunkReviewDao);
        $chunkView->setChunkReviews([]);

        $chunk = $this->createMockJobStruct();
        $project = $this->createStub(ProjectStruct::class);
        $featureSet = $this->createStub(FeatureSet::class);

        $result = $chunkView->renderItem($chunk, $project, $featureSet);

        $this->assertArrayHasKey('quality_summary', $result);
        $this->assertSame([], $result['quality_summary']);
    }

    #[Test]
    public function renderItemWithEmptyReviewsProducesNoRevisePasswords(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')->willReturn($this->makeTteStruct(0));

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkView = $this->createTestableChunk($jobDao, $chunkReviewDao);
        $chunkView->setChunkReviews([]);

        $chunk = $this->createMockJobStruct();
        $project = $this->createStub(ProjectStruct::class);
        $featureSet = $this->createStub(FeatureSet::class);

        $result = $chunkView->renderItem($chunk, $project, $featureSet);

        $this->assertArrayNotHasKey('revise_passwords', $result);
    }

    #[Test]
    public function renderItemIncludesWordCountStats(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getTimeToEdit')->willReturn($this->makeTteStruct(0));

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkView = $this->createTestableChunk($jobDao, $chunkReviewDao);
        $chunkView->setChunkReviews([]);

        $chunk = $this->createMockJobStruct();
        $project = $this->createStub(ProjectStruct::class);
        $featureSet = $this->createStub(FeatureSet::class);

        $result = $chunkView->renderItem($chunk, $project, $featureSet);

        $this->assertArrayHasKey('stats', $result);
        $stats = $result['stats'];
        $this->assertSame(100, $result['id']);
    }
}
