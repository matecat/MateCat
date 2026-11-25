<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobCredentialCacheInvalidator;
use Model\Jobs\JobDao;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TypeError;
use Utils\Constants\SourcePages;

class JobCredentialCacheInvalidatorTest extends AbstractTest
{
    private const int ID_JOB = 4_411;
    private const int ID_PROJECT = 991;

    private const string JOB_PASSWORD = 'job-pw';
    private const string R1_PASSWORD = 'r1-pw';
    private const string R2_PASSWORD = 'r2-pw';

    /**
     * @var list<array{int, string, string|null}>
     */
    private array $jobRowCalls = [];

    /**
     * @var list<array{int, string}>
     */
    private array $jobPasswordSweepCalls = [];

    /**
     * @var list<array{int, string}>
     */
    private array $findChunkReviewsCalls = [];

    /**
     * @var list<array{int, string, int}>
     */
    private array $findChunkReviewsForSourcePageCalls = [];

    /**
     * @var list<array{string, int}>
     */
    private array $reviewPasswordCalls = [];

    /**
     * @var list<array{int, string, int}>
     */

    /**
     * @var list<array{int, string}>
     */
    private array $isTOrR1OrR2Calls = [];

    /**
     * @var list<array{int, string|null}>
     */
    private array $projectCacheCalls = [];

    private function makeInvalidator(): JobCredentialCacheInvalidator
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('destroyCache')
            ->willReturnCallback(function (JobStruct $job, ?string $retiredPassword): void {
                $this->jobRowCalls[] = [(int)$job->id, (string)$job->password, $retiredPassword];
            });

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkReviewDao->method('destroyCachesByJobAndPassword')
            ->willReturnCallback(function (int $id, string $password): void {
                $this->jobPasswordSweepCalls[] = [$id, $password];
            });
        $chunkReviewDao->method('destroyCacheChunkReviews')
            ->willReturnCallback(function (JobStruct $chunk): bool {
                $this->findChunkReviewsCalls[] = [(int)$chunk->id, (string)$chunk->password];

                return true;
            });
        $chunkReviewDao->method('destroyCacheChunkReviewsForSourcePage')
            ->willReturnCallback(function (JobStruct $chunk, int $sourcePage): bool {
                $this->findChunkReviewsForSourcePageCalls[] = [(int)$chunk->id, (string)$chunk->password, $sourcePage];

                return true;
            });
        $chunkReviewDao->method('destroyCacheByReviewPasswordAndJobId')
            ->willReturnCallback(function (string $reviewPassword, int $id): bool {
                $this->reviewPasswordCalls[] = [$reviewPassword, $id];

                return true;
            });
        $chunkReviewDao->method('destroyCacheIsTOrR1OrR2')
            ->willReturnCallback(function (int $id, string $password): bool {
                $this->isTOrR1OrR2Calls[] = [$id, $password];

                return true;
            });
        $chunkReviewDao->method('findByIdJob')->willReturn([
            $this->makeChunkReview(self::R1_PASSWORD, SourcePages::SOURCE_PAGE_REVISION),
            $this->makeChunkReview(self::R2_PASSWORD, SourcePages::SOURCE_PAGE_REVISION_2),
        ]);

        $project = new ProjectStruct();
        $project->id = self::ID_PROJECT;
        $project->password = 'project-pw';

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->method('destroyCache')
            ->willReturnCallback(function (int $id, ?string $password = null): void {
                $this->projectCacheCalls[] = [$id, $password];
            });

        // The metadata eviction has its own real-SQL case; here it only has to be a live double so
        // the credential sweep can run.
        $jobMetadataDao = $this->createStub(JobsMetadataDao::class);

        return new JobCredentialCacheInvalidator($jobDao, $chunkReviewDao, $projectDao, $jobMetadataDao);
    }

    private function makeChunk(string $jobPassword): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = self::ID_JOB;
        $chunk->password = $jobPassword;
        $chunk->id_project = self::ID_PROJECT;

        return $chunk;
    }

    private function makeChunkReview(string $reviewPassword, int $sourcePage): ChunkReviewStruct
    {
        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_job = self::ID_JOB;
        $chunkReview->review_password = $reviewPassword;
        $chunkReview->source_page = $sourcePage;

        return $chunkReview;
    }

    #[Test]
    public function jobPasswordRotation_evicts_the_replaced_and_the_replacing_credential(): void
    {
        // The struct already carries the new credential, so the set of job credentials to evict is
        // exactly the two passed in.
        $this->makeInvalidator()->sweepAfterJobPasswordRotation($this->makeChunk('new-pw'), 'old-pw', 'new-pw');

        // The door takes the chunk as it stands and the credential it replaced: the replacing password
        // is on the struct, the replaced one is reachable nowhere else.
        self::assertSame([[self::ID_JOB, 'new-pw', 'old-pw']], $this->jobRowCalls);
        // destroyCachesByJobAndPassword() is what knows the shapes a job credential keys, the per phase
        // read among them, so the sweep names the credential and nothing else.
        self::assertSame(
            [[self::ID_JOB, 'old-pw'], [self::ID_JOB, 'new-pw']],
            $this->jobPasswordSweepCalls,
            'both the replaced and the replacing job credential must be evicted, and only once each'
        );
        self::assertSame([], $this->findChunkReviewsForSourcePageCalls);
    }

    #[Test]
    public function jobPasswordRotation_evicts_the_reads_keyed_on_every_review_password(): void
    {
        // The rotation renamed the password column of every phase row, so the row a review password
        // read has cached would resolve to no chunk any more.
        $this->makeInvalidator()->sweepAfterJobPasswordRotation($this->makeChunk('new-pw'), 'old-pw', 'new-pw');

        self::assertSame(
            [[self::R1_PASSWORD, self::ID_JOB], [self::R2_PASSWORD, self::ID_JOB]],
            $this->reviewPasswordCalls
        );
    }

    #[Test]
    public function jobPasswordRotation_evicts_the_project_cache_through_the_single_door(): void
    {
        $this->makeInvalidator()->sweepAfterJobPasswordRotation($this->makeChunk('new-pw'), 'old-pw', 'new-pw');

        // getProjectData selects the job password, so its entries have to go. Which keys that means is
        // ProjectDao's own inventory, not something to enumerate from here: one call to the door, and
        // a read added to the DAO later cannot leave a stale entry behind. It also drops the project
        // row, which the rotation left valid, at the price of one miss on a rare event.
        self::assertSame([[self::ID_PROJECT, null]], $this->projectCacheCalls);
    }

    #[Test]
    public function jobPasswordRotation_skips_an_empty_credential(): void
    {
        $this->makeInvalidator()->sweepAfterJobPasswordRotation($this->makeChunk('new-pw'), '', 'new-pw');

        self::assertSame([[self::ID_JOB, 'new-pw', '']], $this->jobRowCalls);
        self::assertSame([[self::ID_JOB, 'new-pw']], $this->jobPasswordSweepCalls);
    }

    #[Test]
    public function jobPasswordRotation_refuses_a_chunk_without_an_id(): void
    {
        $chunk = new JobStruct();
        $chunk->password = 'new-pw';

        $this->expectException(TypeError::class);

        $this->makeInvalidator()->sweepAfterJobPasswordRotation($chunk, 'old-pw', 'new-pw');
    }

    #[Test]
    public function reviewPasswordRotation_evicts_only_the_rotated_phase(): void
    {
        $this->makeInvalidator()->sweepAfterReviewPasswordRotation(
            $this->makeChunk(self::JOB_PASSWORD),
            SourcePages::SOURCE_PAGE_REVISION,
            'old-r1',
            'new-r1'
        );

        self::assertSame([['old-r1', self::ID_JOB], ['new-r1', self::ID_JOB]], $this->reviewPasswordCalls);
        self::assertSame([[self::ID_JOB, 'old-r1'], [self::ID_JOB, 'new-r1']], $this->isTOrR1OrR2Calls);
    }

    #[Test]
    public function reviewPasswordRotation_evicts_the_two_entries_publishing_the_rotated_password(): void
    {
        $this->makeInvalidator()->sweepAfterReviewPasswordRotation(
            $this->makeChunk(self::JOB_PASSWORD),
            SourcePages::SOURCE_PAGE_REVISION_2,
            'old-r2',
            'new-r2'
        );

        // Keyed on the job credential, but their value is where the rotated review password is
        // published: the list of review links and the link handed to the editor of that phase.
        self::assertSame([[self::ID_JOB, self::JOB_PASSWORD]], $this->findChunkReviewsCalls);
        self::assertSame(
            [[self::ID_JOB, self::JOB_PASSWORD, SourcePages::SOURCE_PAGE_REVISION_2]],
            $this->findChunkReviewsForSourcePageCalls
        );
    }

    #[Test]
    public function reviewPasswordRotation_leaves_the_job_row_and_the_project_data_alone(): void
    {
        $this->makeInvalidator()->sweepAfterReviewPasswordRotation(
            $this->makeChunk(self::JOB_PASSWORD),
            SourcePages::SOURCE_PAGE_REVISION,
            'old-r1',
            'new-r1'
        );

        // The job row is untouched by a review password rotation, and getProjectData never selects a
        // review password: evicting either would only cost the other pages their cache.
        self::assertSame([], $this->jobRowCalls);
        self::assertSame([], $this->jobPasswordSweepCalls);
        self::assertSame([], $this->projectCacheCalls);
    }

    #[Test]
    public function reviewPasswordRotation_skips_an_empty_credential(): void
    {
        $this->makeInvalidator()->sweepAfterReviewPasswordRotation(
            $this->makeChunk(self::JOB_PASSWORD),
            SourcePages::SOURCE_PAGE_REVISION,
            '',
            'new-r1'
        );

        self::assertSame([['new-r1', self::ID_JOB]], $this->reviewPasswordCalls);
    }

    #[Test]
    public function reviewPasswordRotation_refuses_a_chunk_without_an_id(): void
    {
        $chunk = new JobStruct();
        $chunk->password = self::JOB_PASSWORD;

        $this->expectException(TypeError::class);

        $this->makeInvalidator()->sweepAfterReviewPasswordRotation(
            $chunk,
            SourcePages::SOURCE_PAGE_REVISION,
            'old-r1',
            'new-r1'
        );
    }
}
