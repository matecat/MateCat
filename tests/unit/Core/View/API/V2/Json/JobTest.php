<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\Job;

#[CoversClass(Job::class)]
class JobTest extends AbstractTest
{
    public function testInstantiationSucceeds(): void
    {
        $view = new Job();
        $this->assertInstanceOf(Job::class, $view);
    }

    public function testInstantiationWithChunkReviewDao(): void
    {
        $dao  = $this->createStub(ChunkReviewDao::class);
        $view = new Job($dao);
        $this->assertInstanceOf(Job::class, $view);
    }

    public function testSetStatusReturnVoid(): void
    {
        $view = new Job();
        $view->setStatus('active');
        // No exception = pass
        $this->assertTrue(true);
    }

    public function testSetUserReturnsSelf(): void
    {
        $view = new Job();
        $user = new UserStruct();
        $ret  = $view->setUser($user);
        $this->assertSame($view, $ret);
    }

    public function testSetCalledFromApiReturnsSelf(): void
    {
        $view = new Job();
        $ret  = $view->setCalledFromApi(true);
        $this->assertSame($view, $ret);
    }

    private function makeProject(int $id = 1, string $password = 'abc'): ProjectStruct
    {
        $project              = new ProjectStruct();
        $project->id          = $id;
        $project->password    = $password;
        $project->id_customer = 'customer1';
        return $project;
    }

    private function makeChunkStub(ProjectStruct $project, int $id = 42, ?string $createDate = '2024-01-15 10:00:00'): JobStruct
    {
        $warningsCount                   = new \stdClass();
        $warningsCount->warnings_count   = 0;
        $warningsCount->warning_segments = [];

        $chunk = $this->createStub(JobStruct::class);
        $chunk->id                      = $id;
        $chunk->password                = 'pass1';
        $chunk->source                  = 'en-US';
        $chunk->target                  = 'it-IT';
        $chunk->status_owner            = 'active';
        $chunk->subject                 = 'general';
        $chunk->owner                   = 'owner@test.com';
        $chunk->create_date             = $createDate;
        $chunk->total_time_to_edit      = 5000;
        $chunk->total_raw_wc            = 100;
        $chunk->standard_analysis_wc    = '95';
        $chunk->job_first_segment       = 1;
        $chunk->avg_post_editing_effort = 0.5;

        $chunk->method('getOutsource')->willReturn(null);
        $chunk->method('getTranslator')->willReturn(null);
        $chunk->method('getWarningsCount')->willReturn($warningsCount);
        $chunk->method('getOpenThreadsCount')->willReturn(0);
        $chunk->method('getPeeForTranslatedSegments')->willReturn(0.0);
        $chunk->method('getQualityOverall')->willReturn('excellent');
        $chunk->method('getErrorsCount')->willReturn(0);
        $chunk->method('getProject')->willReturn($project);
        $chunk->method('getClientKeys')->willReturn(['job_keys' => []]);

        return $chunk;
    }

    private function makeViewWithMockedFillUrls(?ChunkReviewDao $dao = null): Job
    {
        return new class ($dao) extends Job {
            protected function fillUrls(array $result, JobStruct $chunk, ProjectStruct $project, FeatureSet $featureSet): array
            {
                $result['urls'] = [];
                return $result;
            }
        };
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $project  = $this->makeProject();
        $chunk    = $this->makeChunkStub($project);

        $featureSet     = $this->createStub(FeatureSet::class);
        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkReviewDao->method('findChunkReviews')->willReturn([]);
        [$dbStub] = $this->createDatabaseMock();
        $chunkReviewDao->method('getDatabaseHandler')->willReturn($dbStub);

        $view   = $this->makeViewWithMockedFillUrls($chunkReviewDao);
        $result = $view->renderItem($chunk, $project, $featureSet);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['id']);
        $this->assertSame('pass1', $result['password']);
        $this->assertSame('en-US', $result['source']);
        $this->assertSame('it-IT', $result['target']);
        $this->assertSame('active', $result['status']);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('quality_summary', $result);
        $this->assertArrayHasKey('outsource_available', $result);
        $this->assertArrayHasKey('urls', $result);
        $this->assertArrayHasKey('create_timestamp', $result);
    }

    public function testRenderItemWithNullCreateDate(): void
    {
        $project = $this->makeProject();
        $chunk   = $this->makeChunkStub($project, 1, null); // null create_date

        $featureSet     = $this->createStub(FeatureSet::class);
        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkReviewDao->method('findChunkReviews')->willReturn([]);
        [$dbStub] = $this->createDatabaseMock();
        $chunkReviewDao->method('getDatabaseHandler')->willReturn($dbStub);

        $view   = $this->makeViewWithMockedFillUrls($chunkReviewDao);
        $result = $view->renderItem($chunk, $project, $featureSet);

        $this->assertIsArray($result);
        // strtotime('') returns false (falsy) — just verify no TypeError is thrown
        $this->assertArrayHasKey('create_timestamp', $result);
    }

    public function testRenderItemRevisePasswordsPopulated(): void
    {
        $project = $this->makeProject();
        $chunk   = $this->makeChunkStub($project, 7, '2024-06-01 00:00:00');

        $featureSet = $this->createStub(FeatureSet::class);

        $chunkReview                  = new \Model\LQA\ChunkReviewStruct();
        $chunkReview->review_password = 'revpw';
        $chunkReview->source_page     = 2; // SOURCE_PAGE_REVISION = 2

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkReviewDao->method('findChunkReviews')->willReturn([$chunkReview]);
        [$dbStub] = $this->createDatabaseMock();
        $chunkReviewDao->method('getDatabaseHandler')->willReturn($dbStub);

        $view   = $this->makeViewWithMockedFillUrls($chunkReviewDao);
        $result = $view->renderItem($chunk, $project, $featureSet);

        $this->assertArrayHasKey('revise_passwords', $result);
        $this->assertCount(1, $result['revise_passwords']);
        $this->assertSame('revpw', $result['revise_passwords'][0]['password']);
        $this->assertSame(1, $result['revise_passwords'][0]['revision_number']);
    }
}
