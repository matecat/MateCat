<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Model\Analysis\AnalysisDao;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\WordCount\CounterModel;
use DomainException;
use Exception;
use PDO;
use PDOException;
use ReflectionException;
use RuntimeException;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionRepositoryInterface;
use Utils\Logger\LoggerFactory;

class ProjectCompletionRepository implements ProjectCompletionRepositoryInterface
{
    public function __construct(
        private IDatabase $db,
        private ProjectDao $projectDao,
        private JobDao $jobDao,
        private AnalysisDao $analysisDao,
        private CounterModel $counterModel,
    ) {
    }

    /**
     * @throws PDOException
     */
    public function beginTransaction(): void
    {
        $this->db->begin();
    }

    /**
     * @throws PDOException
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * @throws PDOException
     */
    public function rollback(): void
    {
        $this->db->rollback();
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array
    {
        $query = "
            SELECT
                id_job,
                password,
                SUM(eq_word_count) AS eq_wc,
                SUM(standard_word_count) AS st_wc,
                SUM( IF( COALESCE( eq_word_count, 0 ) = 0, raw_word_count, eq_word_count) ) as TOTAL,
                COUNT( s.id ) AS project_segments,
                SUM(IF(st.tm_analysis_status IN ('DONE', 'SKIPPED'), 1, 0)) AS num_analyzed
            FROM segment_translations st
                 JOIN segments s ON s.id = id_segment
                 INNER JOIN jobs j ON j.id=st.id_job
            WHERE j.id_project = :pid
            AND s.show_in_cattool = 1
            GROUP BY id_job WITH ROLLUP
        ";

        try {
            $stmt = $this->db->getConnection()->prepare($query);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute(['pid' => $pid]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            LoggerFactory::doJsonLog($e->getMessage());

            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws PDOException
     */
    public function updateProjectAnalysisStatus(int $pid, string $status, float $eqWc, float $stWc): void
    {
        $this->projectDao->updateFields(
            [
                'status_analysis'      => $status,
                'tm_analysis_wc'       => $eqWc,
                'standard_analysis_wc' => $stWc,
            ],
            ['id' => $pid]
        );
    }

    /**
     * @return array<int, array{id: int, password: string}>
     * @throws ReflectionException
     * @throws Exception
     * @throws DomainException
     */
    public function getProjectJobIds(int $pid): array
    {
        $project = $this->projectDao->findById($pid, ProjectStruct::class);
        assert($project !== null);

        $jobs = $project->getChunks();
        $result = [];

        foreach ($jobs as $job) {
            $result[] = ['id' => (int)$job->id, 'password' => (string)$job->password];
        }

        return $result;
    }

    /**
     * @throws PDOException
     */
    public function updateJobStandardWordCount(int $jobId, float $stWc): void
    {
        $this->jobDao->updateFields(
            ['standard_analysis_wc' => round($stWc)],
            ['id' => $jobId]
        );
    }

    public function initializeJobWordCount(int $jobId, string $password): void
    {
        $this->counterModel->initializeJobWordCount($jobId, $password);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function destroyProjectAndJobCaches(int $pid): void
    {
        $this->projectDao->destroyFindByIdCache($pid, ProjectStruct::class);
        $this->jobDao->destroyCacheByProjectId($pid);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function destroyAllCaches(int $pid, string $projectPassword): void
    {
        $this->projectDao->destroyFindByIdCache($pid, ProjectStruct::class);
        $this->jobDao->destroyCacheByProjectId($pid);
        $this->projectDao->destroyProjectPasswordCache($pid, $projectPassword);
        $this->analysisDao->destroyAnalysisProjectCache($pid);
    }
}
