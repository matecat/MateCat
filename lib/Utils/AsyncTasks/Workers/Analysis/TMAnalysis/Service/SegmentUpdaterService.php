<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Model\DataAccess\Database;
use Model\Translations\SegmentTranslationDao;
use PDOException;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\Logger\LoggerFactory;

class SegmentUpdaterService implements SegmentUpdaterServiceInterface
{
    /**
     * @param array<string, mixed> $tmData
     */
    public function setAnalysisValue(array $tmData): int
    {
        return SegmentTranslationDao::setAnalysisValue($tmData);
    }

    public function forceSetSegmentAnalyzed(int $idSegment, int $idJob, float $rawWordCount): bool
    {
        $data  = ['tm_analysis_status' => 'DONE'];
        $where = ['id_segment' => $idSegment, 'id_job' => $idJob];

        $db = Database::obtain();
        try {
            $affectedRows = $db->update('segment_translations', $data, $where);
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
