<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobCredentialCacheInvalidator;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

class JobCredentialCacheInvalidatorTest extends AbstractTest
{
    private const int ID_JOB = 4_411;
    private const int ID_PROJECT = 991;

    /**
     * @var list<array{int|null, string|null}>
     */
    private array $jobDaoCalls = [];

    /**
     * @var list<array{int, string}>
     */
    private array $chunkReviewDaoCalls = [];

    /**
     * @var list<array{int, string|null}>
     */
    private array $projectDataCalls = [];

    /**
     * @var list<int>
     */
    private array $projectRowCalls = [];

    private function makeInvalidator(): JobCredentialCacheInvalidator
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('destroyCacheForIdAndPassword')
            ->willReturnCallback(function (?int $id, ?string $password): bool {
                $this->jobDaoCalls[] = [$id, $password];

                return true;
            });

        $chunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $chunkReviewDao->method('destroyCacheForJobPassword')
            ->willReturnCallback(function (int $id, string $password): void {
                $this->chunkReviewDaoCalls[] = [$id, $password];
            });

        $project = new ProjectStruct();
        $project->id = self::ID_PROJECT;
        $project->password = 'project-pw';

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->method('destroyCacheForProjectData')
            ->willReturnCallback(function (int $pid, ?string $projectPassword = null): bool {
                $this->projectDataCalls[] = [$pid, $projectPassword];

                return true;
            });
        $projectDao->method('destroyFetchByIdCache')
            ->willReturnCallback(function (int $id, string $fetchClass): bool {
                self::assertSame(ProjectStruct::class, $fetchClass);
                $this->projectRowCalls[] = $id;

                return true;
            });

        return new JobCredentialCacheInvalidator($jobDao, $chunkReviewDao, $projectDao);
    }

    private function makeChunk(string $jobPassword): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = self::ID_JOB;
        $chunk->password = $jobPassword;
        $chunk->id_project = self::ID_PROJECT;

        return $chunk;
    }

    #[Test]
    public function sweepAfterRotation_evicts_the_replaced_and_the_replacing_credential(): void
    {
        // A translate password rotation: the struct already carries the new credential, so the set
        // of affected credentials is exactly the two passed in.
        $this->makeInvalidator()->sweepAfterRotation($this->makeChunk('new-pw'), 'old-pw', 'new-pw');

        self::assertSame(
            [[self::ID_JOB, 'old-pw'], [self::ID_JOB, 'new-pw']],
            $this->jobDaoCalls,
            'both the replaced and the replacing job credential must be evicted, and only once each'
        );
        self::assertSame($this->jobDaoCalls, $this->chunkReviewDaoCalls);
    }

    #[Test]
    public function sweepAfterRotation_also_evicts_the_job_password_of_a_rotated_review_password(): void
    {
        // A review password rotation leaves the job password untouched, but the chunk review rows it
        // keys embed the review passwords, so its entries are stale as well.
        $this->makeInvalidator()->sweepAfterRotation($this->makeChunk('job-pw'), 'old-rev', 'new-rev');

        self::assertSame(
            [[self::ID_JOB, 'old-rev'], [self::ID_JOB, 'new-rev'], [self::ID_JOB, 'job-pw']],
            $this->chunkReviewDaoCalls
        );
    }

    #[Test]
    public function sweepAfterRotation_skips_an_empty_credential(): void
    {
        $this->makeInvalidator()->sweepAfterRotation($this->makeChunk('job-pw'), '', 'new-pw');

        self::assertSame([[self::ID_JOB, 'new-pw'], [self::ID_JOB, 'job-pw']], $this->jobDaoCalls);
    }

    #[Test]
    public function sweepAfterRotation_evicts_both_project_data_shapes_and_the_project_row(): void
    {
        $this->makeInvalidator()->sweepAfterRotation($this->makeChunk('new-pw'), 'old-pw', 'new-pw');

        // getProjectData is read both with and without the project password, so both keys go.
        self::assertSame([[self::ID_PROJECT, null], [self::ID_PROJECT, 'project-pw']], $this->projectDataCalls);
        self::assertSame([self::ID_PROJECT], $this->projectRowCalls);
    }

    #[Test]
    public function sweepAfterRotation_refuses_a_chunk_without_an_id(): void
    {
        $chunk = new JobStruct();
        $chunk->password = 'job-pw';

        $this->expectException(TypeError::class);

        $this->makeInvalidator()->sweepAfterRotation($chunk, 'old-pw', 'new-pw');
    }
}
