<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Model\DataAccess\IDatabase;
use Model\Translations\SegmentTranslationDao;
use PDOException;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\Logger\LoggerFactory;

class SegmentUpdaterService implements SegmentUpdaterServiceInterface
{
    private IDatabase $db;

    public function __construct(IDatabase $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string, mixed> $tmData
     */
    public function setAnalysisValue(array $tmData): int
    {
        return SegmentTranslationDao::setAnalysisValue($tmData);
    }

    public function forceSetSegmentAnalyzed(int $idSegment, int $idJob): bool
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
            LoggerFactory::doJsonLog($e->getMessage());
            LoggerFactory::doJsonLog("**** DB failure in forceSetSegmentAnalyzed for segment $idSegment. NOT incrementing counters.");
            return false;
        }

        if ($affectedRows === 0) {
            LoggerFactory::doJsonLog("Segment $idSegment already DONE, skipping force-set side-effects.");
            return false;
        }

        return true;
    }
}
