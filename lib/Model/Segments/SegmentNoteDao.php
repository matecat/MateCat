<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;
use PDOException;
use ReflectionException;

class SegmentNoteDao extends AbstractDao
{

    /**
     * @param int $id_segment
     * @param int $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public static function getBySegmentId(int $id_segment, int $ttl = 86400): array
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare("SELECT * FROM segment_notes WHERE id_segment = ? ");

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentNoteStruct::class,
            [$id_segment]
        );
    }

    /**
     * @param list<int> $ids
     * @param int $ttl
     *
     * @return list<SegmentNoteStruct>
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public static function getBySegmentIds(array $ids = [], int $ttl = 86400): array
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare("SELECT * FROM segment_notes WHERE id_segment IN ( " . implode(', ', $ids) . " ) ");

        return array_values($thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentNoteStruct::class,
            []
        ));
    }

    /**
     * @param $start int start segment
     * @param $stop  int stop segment
     *
     * @return array<int, list<array{id_segment: int, id: int, note: string}>> array aggregated by id_segment
     * @throws PDOException
     */

    public static function getAggregatedBySegmentIdInInterval(int $start, int $stop): array
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT id_segment, id, note FROM segment_notes " .
            " WHERE id_segment BETWEEN :start AND :stop AND json IS NULL"
        );
        $stmt->execute(['start' => $start, 'stop' => $stop]);

        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }

    /**
     * @param int $start
     * @param int $stop
     *
     * @return array<int, list<array{id_segment: int, id: int, note: string|null, json: string|null}>>
     * @throws PDOException
     */
    public static function getAllAggregatedBySegmentIdInInterval(int $start, int $stop): array
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT id_segment, id, note, json FROM segment_notes " .
            " WHERE id_segment BETWEEN :start AND :stop"
        );
        $stmt->execute(['start' => $start, 'stop' => $stop]);

        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }


    /**
     * @param int $id_segment_start
     * @param int $id_segment_stop
     * @param int $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public static function getJsonNotesByRange(int $id_segment_start, int $id_segment_stop, int $ttl = 0): array
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT id_segment, json FROM segment_notes WHERE id_segment BETWEEN :start AND :stop AND note IS NULL ");

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentNoteStruct::class,
            [
                'start' => $id_segment_start,
                'stop' => $id_segment_stop
            ]
        );
    }

}
