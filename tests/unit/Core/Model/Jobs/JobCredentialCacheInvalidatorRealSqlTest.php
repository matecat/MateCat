<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Jobs\JobCredentialCacheInvalidator;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\SourcePages;

#[Group('DaoRealSql')]
#[Group('PersistenceNeeded')]
class JobCredentialCacheInvalidatorRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const string R1_PASSWORD = 'jcci_r1_pwd';
    private const string R2_PASSWORD = 'jcci_r2_pwd';

    private int $idProject;
    private int $idJob;
    private string $jobPassword;

    private ChunkReviewDao $chunkReviewDao;
    private JobDao $jobDao;
    private JobCredentialCacheInvalidator $invalidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startRealSql([
            'qa_chunk_reviews', 'jobs', 'projects', 'segments', 'segment_translations',
            'files', 'files_job', 'users',
        ]);

        $user = $this->fixtures->makeUser();
        // the column is nullable, the struct is not: a project always carries a password
        $project = $this->fixtures->makeProject(['password' => 'jcci_project_pwd']);
        $this->idProject = $project['id'];
        $file = $this->fixtures->makeFile($this->idProject);
        $segment = $this->fixtures->makeSegment($file['id']);

        $job = $this->fixtures->makeJob($this->idProject, [
            'owner' => $user['email'],
            'job_first_segment' => $segment['id'],
            'job_last_segment' => $segment['id'],
        ]);
        $this->idJob = $job['id'];
        $this->jobPassword = $job['password'];
        $this->fixtures->makeFilesJob($this->idJob, $file['id']);

        // one review row per phase, each with its own review password, which is what a phase scoped
        // eviction has to be able to tell apart
        $this->fixtures->makeQaChunkReview($this->idProject, $this->idJob, $this->jobPassword, [
            'review_password' => self::R1_PASSWORD,
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $this->fixtures->makeQaChunkReview($this->idProject, $this->idJob, $this->jobPassword, [
            'review_password' => self::R2_PASSWORD,
            'source_page' => SourcePages::SOURCE_PAGE_REVISION_2,
        ]);

        $this->chunkReviewDao = new ChunkReviewDao($this->realSqlDb());
        $this->jobDao = new JobDao($this->realSqlDb());
        $this->invalidator = new JobCredentialCacheInvalidator(
            $this->jobDao,
            $this->chunkReviewDao,
            new ProjectDao($this->realSqlDb())
        );
    }

    private function chunk(): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = $this->idJob;
        $chunk->password = $this->jobPassword;
        $chunk->id_project = $this->idProject;

        return $chunk;
    }

    private function reviewRowFor(string $reviewPassword): ?ChunkReviewStruct
    {
        // The phase is whatever row the password matches, so the credential alone keys this read.
        return $this->chunkReviewDao->findByReviewPasswordAndJobId($reviewPassword, $this->idJob, 3600);
    }

    #[Test]
    public function jobPasswordRotation_stops_the_editor_read_of_the_replaced_password(): void
    {
        // The editor authenticates through this read, and its callers cache it for up to a day: the
        // link built on the replaced password has to stop resolving at once, not when the TTL runs out.
        $jStruct = $this->jobDao->getByIdAndPassword($this->idJob, $this->jobPassword, 86400);
        $this->assertNotNull($jStruct);

        $rotated = 'jcci_rotated_pwd';
        $this->jobDao->changePassword($jStruct, $rotated);

        $this->assertNotNull(
            $this->jobDao->getByIdAndPassword($this->idJob, $this->jobPassword, 86400),
            'the rotation alone leaves the replaced password authenticating out of the cache'
        );

        $this->invalidator->sweepAfterJobPasswordRotation($jStruct, $this->jobPassword, $rotated);

        $this->assertNull($this->jobDao->getByIdAndPassword($this->idJob, $this->jobPassword, 86400));
        $this->assertNotNull($this->jobDao->getByIdAndPassword($this->idJob, $rotated, 86400));
    }

    #[Test]
    public function jobPasswordRotation_evicts_a_miss_cached_for_the_replacing_password(): void
    {
        $jStruct = $this->jobDao->getByIdAndPassword($this->idJob, $this->jobPassword);
        $this->assertNotNull($jStruct);

        // a lookup made before the rotation may have cached the miss of the incoming password
        $rotated = 'jcci_rotated_pwd';
        $this->assertNull($this->jobDao->getByIdAndPassword($this->idJob, $rotated, 86400));

        $this->jobDao->changePassword($jStruct, $rotated);
        $this->invalidator->sweepAfterJobPasswordRotation($jStruct, $this->jobPassword, $rotated);

        $this->assertNotNull($this->jobDao->getByIdAndPassword($this->idJob, $rotated, 86400));
    }

    #[Test]
    public function jobPasswordRotation_stops_the_review_reads_pointing_at_the_replaced_password(): void
    {
        $warm = $this->reviewRowFor(self::R1_PASSWORD);
        $this->assertNotNull($warm);
        $this->assertSame($this->jobPassword, $warm->password);

        // what the API controller and a translator being replaced both do: rotate the job row, then
        // mirror the new password onto the review rows
        $jStruct = $this->jobDao->getByIdAndPassword($this->idJob, $this->jobPassword);
        $this->assertNotNull($jStruct);
        $rotated = 'jcci_rotated_pwd';
        $this->jobDao->changePassword($jStruct, $rotated);
        $this->assertSame(2, $this->chunkReviewDao->updatePassword($this->idJob, $this->jobPassword, $rotated));

        $stale = $this->reviewRowFor(self::R1_PASSWORD);
        $this->assertNotNull($stale);
        $this->assertSame(
            $this->jobPassword,
            $stale->password,
            'the rotation alone leaves the review read holding the replaced job password, so the chunk it resolves is gone'
        );

        $this->invalidator->sweepAfterJobPasswordRotation($jStruct, $this->jobPassword, $rotated);

        $fresh = $this->reviewRowFor(self::R1_PASSWORD);
        $this->assertNotNull($fresh);
        $this->assertSame($rotated, $fresh->password, 'the review link has to resolve the chunk again right after the rotation');

        $second = $this->reviewRowFor(self::R2_PASSWORD);
        $this->assertNotNull($second);
        $this->assertSame($rotated, $second->password, 'the second pass link is keyed on its own password and goes stale as well');
    }

    #[Test]
    public function reviewPasswordRotation_leaves_the_other_phase_served(): void
    {
        $chunk = $this->chunk();

        $this->assertNotNull($this->reviewRowFor(self::R1_PASSWORD));
        $this->assertNotNull($this->reviewRowFor(self::R2_PASSWORD));
        $this->assertCount(
            1,
            $this->chunkReviewDao->findChunkReviewsForSourcePage($chunk, SourcePages::SOURCE_PAGE_REVISION_2, 3600)
        );

        // both phases are rotated in the database, and only the first pass is swept: what still
        // answers afterwards is being served from the entries the sweep did not touch
        $this->assertSame(1, $this->chunkReviewDao->updateReviewPassword(
            $this->idJob,
            self::R1_PASSWORD,
            'jcci_r1_rotated',
            SourcePages::SOURCE_PAGE_REVISION
        ));
        $this->assertSame(1, $this->chunkReviewDao->updateReviewPassword(
            $this->idJob,
            self::R2_PASSWORD,
            'jcci_r2_rotated',
            SourcePages::SOURCE_PAGE_REVISION_2
        ));

        $this->invalidator->sweepAfterReviewPasswordRotation(
            $chunk,
            SourcePages::SOURCE_PAGE_REVISION,
            self::R1_PASSWORD,
            'jcci_r1_rotated'
        );

        $this->assertNull(
            $this->reviewRowFor(self::R1_PASSWORD),
            'the replaced first pass password must stop resolving a review'
        );
        $this->assertNotNull(
            $this->reviewRowFor(self::R2_PASSWORD),
            'the second pass entry belongs to another phase and must survive'
        );
        $this->assertCount(
            1,
            $this->chunkReviewDao->findChunkReviewsForSourcePage($chunk, SourcePages::SOURCE_PAGE_REVISION_2, 3600),
            'the second pass review link the editor is handed must survive too'
        );
    }

    #[Test]
    public function reviewPasswordRotation_evicts_what_publishes_the_rotated_password(): void
    {
        $chunk = $this->chunk();

        $this->assertCount(
            1,
            $this->chunkReviewDao->findChunkReviewsForSourcePage($chunk, SourcePages::SOURCE_PAGE_REVISION, 3600)
        );
        $this->assertCount(2, $this->chunkReviewDao->findChunkReviews($chunk, 3600));

        $this->assertSame(1, $this->chunkReviewDao->updateReviewPassword(
            $this->idJob,
            self::R1_PASSWORD,
            'jcci_r1_rotated',
            SourcePages::SOURCE_PAGE_REVISION
        ));

        $this->invalidator->sweepAfterReviewPasswordRotation(
            $chunk,
            SourcePages::SOURCE_PAGE_REVISION,
            self::R1_PASSWORD,
            'jcci_r1_rotated'
        );

        // both reads are keyed on the job credential, but the rotated review password is what they
        // publish: the list of review links, and the link handed to the editor of that phase
        $editorLink = $this->chunkReviewDao->findChunkReviewsForSourcePage($chunk, SourcePages::SOURCE_PAGE_REVISION, 3600);
        $this->assertCount(1, $editorLink);
        $this->assertSame('jcci_r1_rotated', $editorLink[0]->review_password);

        $allLinks = $this->chunkReviewDao->findChunkReviews($chunk, 3600);
        $this->assertCount(2, $allLinks);
        $this->assertSame('jcci_r1_rotated', $allLinks[0]->review_password);
        $this->assertSame(self::R2_PASSWORD, $allLinks[1]->review_password);
    }
}
