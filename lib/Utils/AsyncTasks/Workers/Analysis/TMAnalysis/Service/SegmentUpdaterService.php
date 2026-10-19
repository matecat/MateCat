<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Closure;
use Exception;
use Model\DataAccess\IDatabase;
use Model\Translations\SegmentTranslationDao;
use PDOException;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;

class SegmentUpdaterService implements SegmentUpdaterServiceInterface
{
    private IDatabase $db;

    /**
     * Sink for log messages. Injected by the worker so these lines land in the analysis-queue
     * log, next to the rest of the job's lines. Null skips logging (unit tests).
     *
     * @var (Closure(string): void)|null
     */
    private ?Closure $logger;

    /**
     * @param (Closure(string): void)|null $logger
     */
    public function __construct(IDatabase $db, ?Closure $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    private function log(string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message);
        }
    }

    /**
     * @param array<string, mixed> $tmData
     * @throws PDOException
     * @throws Exception
     */
    public function setAnalysisValue(array $tmData): int
    {
        return (new SegmentTranslationDao($this->db))->setAnalysisValue($tmData);
    }

    /**
     * @param int $idSegment
     * @param int $idJob
     * @return int
     * @throws PDOException
     */
    public function forceSetSegmentAnalyzed(int $idSegment, int $idJob): int
    {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE segment_translations
                    SET tm_analysis_status = 'DONE'
                  WHERE id_segment = :id_segment
                    AND id_job = :id_job
                    AND tm_analysis_status NOT IN ('DONE', 'SKIPPED')"
            );
            $stmt->execute([':id_segment' => $idSegment, ':id_job' => $idJob]);
            $affectedRows = $stmt->rowCount();
        } catch (PDOException $e) {
            $this->log($e->getMessage());
            $this->log("**** DB failure in forceSetSegmentAnalyzed for segment $idSegment. NOT incrementing counters.");
            return 0;
        }

        if ($affectedRows === 0) {
            return (new SegmentTranslationDao($this->db))->isTranslationSkipped(
                $idSegment,
                $idJob
            ) ? -1 : 0;
        }

        return $affectedRows;
    }
}
