<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Model\DataAccess\IDatabase;
use Model\Translations\SegmentTranslationDao;
use PDOException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SegmentUpdaterService
{
    private IDatabase $db;

    /**
     * Where the service logs. The worker passes its queue logger, so these lines land in the
     * analysis-queue log. The default NullLogger drops them (unit tests).
     */
    private LoggerInterface $logger;

    public function __construct(IDatabase $db, LoggerInterface $logger = new NullLogger())
    {
        $this->db = $db;
        $this->logger = $logger;
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
            $this->logger->error("DB failure in forceSetSegmentAnalyzed for segment $idSegment. NOT incrementing counters: " . $e->getMessage());
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
