<?php

namespace Model\Jobs;

use Exception;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use PDOException;
use ReflectionException;
use TypeError;

/**
 * Evicts every cached read a rotated job credential is the key or the content of.
 *
 * The sweep has to run once the rotation is committed. While the transaction is still open every
 * other connection keeps reading the pre-rotation row, so a request presenting the replaced
 * password misses the cache, resolves the job against that row and caches the credential as valid
 * again, for the whole TTL, behind the eviction that has just run.
 *
 * The two rotations are not symmetric, and each evicts only what its own credential reaches:
 *
 * - the job password lives in jobs.password and, mirrored, in the password column of every phase row
 *   of qa_chunk_reviews, so rotating it stales the reads keyed on the job credential and every read
 *   keyed on a review password, whose cached row would keep pointing at the replaced job password;
 * - a review password is one column of one phase row, so rotating it leaves the other phases and the
 *   job row alone. Beyond its own keys only the two entries that carry it in their value go: the
 *   list of review links and the review link the editor of that phase is handed.
 */
readonly class JobCredentialCacheInvalidator
{
    public function __construct(
        private JobDao $jobDao,
        private ChunkReviewDao $chunkReviewDao,
        private ProjectDao $projectDao,
    ) {
    }

    /**
     * @param JobStruct $chunk The chunk as it stands after the rotation.
     * @param string $oldPassword The job password that was replaced.
     * @param string $newPassword The job password replacing it: it may hold a miss cached by a
     *                            lookup made before the rotation.
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    public function sweepAfterJobPasswordRotation(JobStruct $chunk, string $oldPassword, string $newPassword): void
    {
        $idJob = $chunk->id ?? throw new TypeError('JobStruct::$id cannot be null');

        foreach ($this->credentials($oldPassword, $newPassword) as $password) {
            $this->jobDao->destroyCacheForIdAndPassword($idJob, $password);
            $this->chunkReviewDao->destroyCacheForJobPassword($idJob, $password);
        }

        // The rotation renamed the password column of every phase row, so the reads keyed on a review
        // password hold a row that resolves to no chunk any more: the review links would answer 404
        // until their entries expired. findByIdJob() is not cached, and running after the commit it
        // returns the phases as they stand now.
        foreach ($this->chunkReviewDao->findByIdJob($idJob) as $chunkReview) {
            $reviewPassword = (string)$chunkReview->review_password;
            if ($reviewPassword === '') {
                continue;
            }

            $this->chunkReviewDao->destroyCacheForReviewPasswordAndJobId($reviewPassword, $idJob);
        }

        // Project data is cached for a day and embeds the job password. The list of the project's
        // jobs is keyed on the project, not on a credential, but it is the rows themselves that are
        // cached, so it publishes the password too.
        $this->destroyProjectDataCache($chunk);
        $this->jobDao->destroyCacheByProjectId(
            $chunk->id_project ?? throw new TypeError('JobStruct::$id_project cannot be null')
        );
    }

    /**
     * @param JobStruct $chunk The chunk the rotated phase belongs to.
     * @param int $sourcePage The phase whose review password was rotated.
     * @param string $oldPassword The review password that was replaced.
     * @param string $newPassword The review password replacing it: it may hold a miss cached by a
     *                            lookup made before the rotation.
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    public function sweepAfterReviewPasswordRotation(JobStruct $chunk, int $sourcePage, string $oldPassword, string $newPassword): void
    {
        $idJob = $chunk->id ?? throw new TypeError('JobStruct::$id cannot be null');
        $jobPassword = (string)$chunk->password;

        foreach ($this->credentials($oldPassword, $newPassword) as $password) {
            $this->chunkReviewDao->destroyCacheForReviewPasswordAndJobId($password, $idJob);
            $this->chunkReviewDao->destroyCacheForIsTOrR1OrR2($idJob, $password);
        }

        // These two are keyed on the job credential, so they belong to the other pages as much as to
        // this one, but their value is where the rotated review password is published: the list of
        // review links, and the link the editor of this phase is handed. Nothing else the job
        // credential keys carries a review password, and no other phase is touched.
        if ($jobPassword !== '') {
            $this->chunkReviewDao->destroyCacheForFindChunkReviews($chunk);
            $this->chunkReviewDao->destroyCacheForFindChunkReviewsForSourcePage($chunk, $sourcePage);
        }
    }

    /**
     * @return list<string>
     */
    private function credentials(string $oldPassword, string $newPassword): array
    {
        return array_values(array_filter(array_unique([$oldPassword, $newPassword])));
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeError
     */
    private function destroyProjectDataCache(JobStruct $chunk): void
    {
        $project = $chunk->getProject($this->projectDao);
        $projectId = $project->id ?? throw new TypeError('ProjectStruct::$id cannot be null');

        $this->projectDao->destroyCacheForProjectData($projectId);
        $this->projectDao->destroyCacheForProjectData($projectId, $project->password);
    }
}
