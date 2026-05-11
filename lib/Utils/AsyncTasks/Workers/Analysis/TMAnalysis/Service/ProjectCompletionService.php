<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Model\Analysis\AnalysisDao;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\WordCount\CounterModel;
use PDO;
use PDOException;
use RuntimeException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\Constants\ProjectStatus;
use Utils\Logger\LoggerFactory;

class ProjectCompletionService implements ProjectCompletionServiceInterface
{
    private AnalysisRedisServiceInterface $redisService;

    public function __construct(AMQHandler $queueHandler, AnalysisRedisServiceInterface $redisService)
    {
        LoggerFactory::doJsonLog('ProjectCompletionService initialized with queue handler ' . get_class($queueHandler));
        $this->redisService = $redisService;
    }

    public function tryCloseProject(int $pid, string $projectPassword, string $queueKey, FeatureSet $featureSet): void
    {
        $projectTotals = $this->redisService->getProjectWordCounts($pid);

        LoggerFactory::doJsonLog("--- trying to close project {$pid}: project_segments=" . ($projectTotals['project_segments'] ?? 'NULL'));
        LoggerFactory::doJsonLog("--- trying to close project {$pid}: num_analyzed=" . ($projectTotals['num_analyzed'] ?? 'NULL'));

        if (empty($projectTotals['project_segments'])) {
            LoggerFactory::doJsonLog("--- WARNING !!! error while counting segments in project {$pid}, skipping and continue");
            return;
        }

        if ((int)$projectTotals['project_segments'] - (int)$projectTotals['num_analyzed'] === 0
            && $this->redisService->acquireCompletionLock($pid)
        ) {
            try {
                $this->redisService->removeProjectFromQueue($queueKey, $pid);

                LoggerFactory::doJsonLog("--- trying to initialize job total word count for project {$pid}.");

                $database = Database::obtain();
                $database->begin();

                $_full_report = $this->getProjectSegmentsTranslationSummary($pid);
                $rollup       = array_pop($_full_report);
                assert($rollup !== null);
                $_analyzed_report = $_full_report;

                LoggerFactory::doJsonLog("--- analysis project {$pid} finished: change status to DONE");

                ProjectDao::updateFields(
                    [
                        'status_analysis' => ProjectStatus::STATUS_DONE,
                        'tm_analysis_wc' => $rollup['eq_wc'],
                        'standard_analysis_wc' => $rollup['st_wc']
                    ],
                    ['id' => $pid]
                );

                $project = ProjectDao::findById($pid);
                assert($project !== null);
                $jobs         = $project->getChunks();
                $numberOfJobs = count($jobs);

                foreach ($jobs as $job) {
                    JobDao::updateFields([
                        'standard_analysis_wc' => round($rollup['st_wc'] / $numberOfJobs)
                    ], [
                        'id' => $job->id
                    ]);
                }

                ProjectDao::destroyCacheById($pid);
                (new JobDao())->destroyCacheByProjectId($pid);

                foreach ($_analyzed_report as $job_info) {
                    $counter = new CounterModel();
                    $counter->initializeJobWordCount($job_info['id_job'], $job_info['password']);
                }

                $database->commit();

                try {
                    $featureSet->run('afterTMAnalysisCloseProject', $pid, $_analyzed_report);
                } catch (Exception $e) {
                    LoggerFactory::doJsonLog("Ending project_id {$pid} with error {$e->getMessage()} . COMPLETED.");
                }

                (new JobDao())->destroyCacheByProjectId($pid);
                ProjectDao::destroyCacheById($pid);
                ProjectDao::destroyCacheByIdAndPassword($pid, $projectPassword);
                AnalysisDao::destroyCacheByProjectId($pid);
            } catch (\Throwable $e) {
                LoggerFactory::doJsonLog("**** Finalization failed for project {$pid}: " . $e->getMessage());

                try {
                    Database::obtain()->rollback();
                } catch (\Throwable) {
                    // Already rolled back or no active transaction
                }

                $this->redisService->releaseCompletionLock($pid);
                $this->redisService->reAddProjectToQueue($queueKey, $pid);
            }
        }
    }

    /**
     * This function is heavy, use, but only if it is necessary
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    private function getProjectSegmentsTranslationSummary(int $pid): array
    {
        //TOTAL and eq_word should be equals, BUT
        //tm Analysis can fail on some rows because of external service nature, so use TOTAL field instead of eq_word
        //to set the global word counter in job
        //Ref: jobs.new_words
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
            $db = Database::obtain();
            $stmt = $db->getConnection()->prepare($query);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute(['pid' => $pid]);
            $results = $stmt->fetchAll();
        } catch (PDOException $e) {
            LoggerFactory::doJsonLog($e->getMessage());

            throw new RuntimeException($e);
        }

        return $results;
    }
}
