<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:20
 */

namespace Plugins\Features\TranslationEvents\Model;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use PDO;
use ReflectionException;
use Utils\Constants\TranslationStatus;

class TranslationEventDao extends AbstractDao
{

    const string TABLE       = "segment_translation_events";
    const string STRUCT_TYPE = TranslationEventStruct::class;

    protected static array $auto_increment_field = ['id'];
    protected static array $primary_keys         = ['id'];

    public function unsetFinalRevisionFlag(int $id_job, array $id_segments, array $source_pages): int
    {
        $sql = " UPDATE segment_translation_events SET final_revision = 0 " .
                " WHERE id_job = :id_job " .
                " AND id_segment IN ( " . implode(',', $id_segments) . " ) " .
                " AND source_page IN ( " . implode(',', $source_pages) . " ) ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->execute([
                'id_job' => $id_job,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param int $id_job
     * @param int $min_segment
     * @param int $max_segment
     *
     * @return TranslationEventStruct[]
     */
    public function getLatestEventsInSegmentInterval(int $id_job, int $min_segment, int $max_segment): array
    {
        $sql = "SELECT * FROM  segment_translation_events 
                JOIN (
                        SELECT max(id) as _m_id FROM segment_translation_events
                        WHERE id_job = :id_job
                        AND id_segment BETWEEN :min_segment AND :max_segment
                        GROUP BY id_segment 
                ) AS X ON _m_id = segment_translation_events.id
                ORDER BY id_segment";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, TranslationEventStruct::class);
        $stmt->execute([
                'id_job'      => $id_job,
                'min_segment' => $min_segment,
                'max_segment' => $max_segment
        ]);

        return $stmt->fetchAll();
    }

    /**
     * @param int $id_job
     * @param int $id_segment
     *
     * @return TranslationEventStruct[]
     */
    public function getAllFinalRevisionsForSegment(int $id_job, int $id_segment): array
    {
        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND status != :draft
                    AND final_revision = 1
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, TranslationEventStruct::class);
        $stmt->execute([
                'id_job'     => $id_job,
                'id_segment' => $id_segment,
                'draft'      => TranslationStatus::STATUS_DRAFT
        ]);

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $id_segment
     *
     * @return TranslationEventStruct|null
     */
    public function getLatestEventForSegment($id_job, $id_segment): ?TranslationEventStruct
    {
        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND status != :draft
                    ORDER BY id DESC
                    LIMIT 1
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, TranslationEventStruct::class);
        $stmt->execute([
                'id_job'     => $id_job,
                'id_segment' => $id_segment,
                'draft'      => TranslationStatus::STATUS_DRAFT
        ]);

        $res = $stmt->fetchAll();

        return $res[ 0 ] ?? null;
    }

    /**
     * @param array $id_segment_list
     * @param int   $id_job
     *
     * @return ShapelessConcreteStruct[]|null
     * @throws ReflectionException
     */
    public function getTteForSegments(array $id_segment_list, int $id_job): ?array
    {
        $in  = str_repeat('?,', count($id_segment_list) - 1) . '?';
        $sql = "
            SELECT 
                    id_segment, 
                    SUM( time_to_edit ) AS tte, 
                    source_page
                FROM
                    segment_translation_events
                WHERE
                    id_segment IN ( $in )
                AND id_job = ?
                GROUP BY id_segment, source_page
                ORDER BY id_segment, source_page
        ";

        $stmt              = $this->_getStatementForQuery($sql);
        $id_segment_list[] = $id_job;

        return $this->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $id_segment_list) ?? null;
    }

}