<?php

namespace Model\Jobs;

use Exception;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDOException;
use ReflectionException;
use TypeError;

/**
 * Evicts every cached read a rotated job credential is the key of.
 *
 * The sweep has to run once the rotation is committed. While the transaction is still open every
 * other connection keeps reading the pre-rotation row, so a request presenting the replaced
 * password misses the cache, resolves the job against that row and caches the credential as valid
 * again, for the whole TTL, behind the eviction that has just run.
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
     * @param string ...$rotatedPasswords The credential that was replaced and the one replacing it:
     *                                    the latter may hold a miss cached by a lookup made before
     *                                    the rotation.
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    public function sweepAfterRotation(JobStruct $chunk, string ...$rotatedPasswords): void
    {
        $idJob = $chunk->id ?? throw new TypeError('JobStruct::$id cannot be null');

        // The chunk review rows carry the review passwords, so the reads keyed on the job's own
        // credential go stale after a review password rotation too.
        $credentials = array_filter(array_unique([...$rotatedPasswords, (string)$chunk->password]));

        foreach ($credentials as $password) {
            $this->jobDao->destroyCacheForIdAndPassword($idJob, $password);
            $this->chunkReviewDao->destroyCacheForJobPassword($idJob, $password);
        }

        // Project data is cached for a day and embeds the job passwords.
        $project = $chunk->getProject($this->projectDao);
        $projectId = $project->id ?? throw new TypeError('ProjectStruct::$id cannot be null');

        $this->projectDao->destroyCacheForProjectData($projectId);
        $this->projectDao->destroyCacheForProjectData($projectId, $project->password);
        $this->projectDao->destroyFetchByIdCache($projectId, ProjectStruct::class);
    }
}
