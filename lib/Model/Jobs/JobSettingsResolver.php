<?php

namespace Model\Jobs;

use Exception;
use Model\DataAccess\IDatabase;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use PDOException;
use ReflectionException;

/**
 * Resolves a job-scoped setting through the fallback chain
 *
 *      job metadata  ->  project metadata  ->  null
 *
 * MT tuning settings (DeepL formality, Lara style, the MT application threshold, …) used to be
 * written to `project_metadata` at creation time only, which made them immutable for the whole
 * life of the project. They are now written per job, so the project owner can change them
 * afterwards.
 *
 * Production holds billions of `project_metadata` rows, so those cannot be migrated: every read
 * has to keep answering from project metadata whenever the job has no row of its own. That is
 * the only reason this class exists, and it is why the project read must stay reachable
 * indefinitely rather than behind a deprecation window.
 *
 * @see \Model\Jobs\JobsMetadataMarshaller::mtSettings()
 */
readonly class JobSettingsResolver
{
    /**
     * The TTL every engine already used for its direct project-metadata read.
     */
    public const int DEFAULT_TTL = 86400;

    private MetadataDao $jobMetadataDao;
    private ProjectsMetadataDao $projectMetadataDao;

    public function __construct(
        IDatabase            $database,
        ?MetadataDao         $jobMetadataDao = null,
        ?ProjectsMetadataDao $projectMetadataDao = null,
    ) {
        $this->jobMetadataDao     = $jobMetadataDao ?? new MetadataDao($database);
        $this->projectMetadataDao = $projectMetadataDao ?? new ProjectsMetadataDao($database);
    }

    /**
     * @param int|null    $idJob     null/0 when the caller has no job context (engine validators)
     * @param string|null $password  the job (chunk) password, part of the job_metadata key
     * @param int|null    $idProject null/non-positive to skip the project fallback entirely
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function resolve(?int $idJob, ?string $password, ?int $idProject, string $key, int $ttl = self::DEFAULT_TTL): mixed
    {
        if (!empty($idJob) && !empty($password)) {
            $struct = $this->jobMetadataDao->get($idJob, $password, $key, $ttl);
            if ($struct !== null) {
                return JobsMetadataMarshaller::unMarshall($struct);
            }
        }

        // A non-positive id is never a real project: engine validators fabricate a negative one
        // precisely so no stored setting is picked up (@see DeepLEngineValidator).
        if ($idProject !== null && $idProject > 0) {
            return $this->projectMetadataDao->setCacheTTL($ttl)->getValue($idProject, $key);
        }

        return null;
    }

    /**
     * Resolve several keys with two bulk reads instead of two per key. Use this on the paths that
     * need the whole MT settings set at once (project analysis publishing, the metadata endpoint).
     *
     * Keys absent from both scopes are absent from the returned map, so `??` still applies the
     * caller's own default.
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function resolveMany(?int $idJob, ?string $password, ?int $idProject, array $keys, int $ttl = self::DEFAULT_TTL): array
    {
        $wanted = array_flip($keys);

        $resolved = [];

        if ($idProject !== null && $idProject > 0) {
            $resolved = array_intersect_key(
                $this->projectMetadataDao->setCacheTTL($ttl)->allByProjectIdAsKeyValue($idProject),
                $wanted
            );
        }

        if (!empty($idJob) && !empty($password)) {
            foreach ($this->jobMetadataDao->getByJobIdAndPassword($idJob, $password, $ttl) as $struct) {
                // getByJobIdAndPassword() has already un-marshalled every row.
                if (isset($wanted[$struct->key])) {
                    $resolved[$struct->key] = $struct->value;
                }
            }
        }

        return $resolved;
    }

    /**
     * Resolve a setting from an engine `$_config` array.
     *
     * The engines disagree on how the project id reaches them — DeepL and Intento read `pid`,
     * Lara and MMT read `id_project` — so both are accepted here and a caller that populates
     * only one of them no longer silently loses half of the settings.
     *
     * @param array<string, mixed> $config
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function resolveFromEngineConfig(array $config, string $key, int $ttl = self::DEFAULT_TTL): mixed
    {
        return $this->resolve(
            isset($config['job_id']) && is_numeric($config['job_id']) ? (int)$config['job_id'] : null,
            isset($config['job_password']) && is_scalar($config['job_password']) ? (string)$config['job_password'] : null,
            self::projectIdFromEngineConfig($config),
            $key,
            $ttl
        );
    }

    /**
     * {@see self::resolveMany()} for an engine `$_config` array. Prefer this over repeated
     * {@see self::resolveFromEngineConfig()} calls on the per-segment MT paths: it costs two
     * lookups for the whole set instead of two per key.
     *
     * @param array<string, mixed> $config
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function resolveManyFromEngineConfig(array $config, array $keys, int $ttl = self::DEFAULT_TTL): array
    {
        return $this->resolveMany(
            isset($config['job_id']) && is_numeric($config['job_id']) ? (int)$config['job_id'] : null,
            isset($config['job_password']) && is_scalar($config['job_password']) ? (string)$config['job_password'] : null,
            self::projectIdFromEngineConfig($config),
            $keys,
            $ttl
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function projectIdFromEngineConfig(array $config): ?int
    {
        foreach (['id_project', 'pid'] as $candidate) {
            if (isset($config[$candidate]) && is_numeric($config[$candidate])) {
                return (int)$config[$candidate];
            }
        }

        return null;
    }
}
