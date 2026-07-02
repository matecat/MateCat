<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use PDO;

class SegmentNoteDao extends AbstractDao
{

    /**
     * @param int $id_segment
     * @param int $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws \ReflectionException
     * @throws \PDOException
     * @throws \Exception
     */
    public function getBySegmentId(int $id_segment, int $ttl = 86400): array
    {
        $stmt = $this->database->getConnection()->prepare("SELECT * FROM segment_notes WHERE id_segment = ? ");

        return $this->setCacheTTL($ttl)->_fetchObjectMap(
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
     * @throws \ReflectionException
     * @throws \PDOException
     * @throws \Exception
     */
    public function getBySegmentIds(array $ids = [], int $ttl = 86400): array
    {
        $stmt = $this->database->getConnection()->prepare("SELECT * FROM segment_notes WHERE id_segment IN ( " . implode(', ', $ids) . " ) ");

        return array_values($this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentNoteStruct::class,
            []
        ));
    }

    /**
     * @param int $start
     * @param int $stop
     *
     * @return array<int, list<array{id_segment: int, id: int, note: string}>>
     * @throws \PDOException
     */
    public function getAggregatedBySegmentIdInInterval(int $start, int $stop): array
    {
        $stmt = $this->database->getConnection()->prepare(
            "SELECT id_segment, id, note FROM segment_notes " .
            " WHERE id_segment BETWEEN :start AND :stop AND json IS NULL"
        );
        $stmt->execute(['start' => $start, 'stop' => $stop]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['id_segment']][] = $row;
        }
        return $grouped;
    }

    /**
     * @param int $start
     * @param int $stop
     *
     * @return array<int, list<array{id_segment: int, id: int, note: string|null, json: string|null}>>
     * @throws \PDOException
     */
    public function getAllAggregatedBySegmentIdInInterval(int $start, int $stop): array
    {
        $stmt = $this->database->getConnection()->prepare(
            "SELECT id_segment, id, note, json FROM segment_notes " .
            " WHERE id_segment BETWEEN :start AND :stop"
        );
        $stmt->execute(['start' => $start, 'stop' => $stop]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row['id_segment']][] = $row;
        }
        return $grouped;
    }

    /**
     * @param int $id_segment_start
     * @param int $id_segment_stop
     * @param int $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws \ReflectionException
     * @throws \PDOException
     * @throws \Exception
     */
    public function getJsonNotesByRange(int $id_segment_start, int $id_segment_stop, int $ttl = 0): array
    {
        $stmt = $this->database->getConnection()->prepare("SELECT id_segment, json FROM segment_notes WHERE id_segment BETWEEN :start AND :stop AND note IS NULL ");

        return $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentNoteStruct::class,
            [
                'start' => $id_segment_start,
                'stop' => $id_segment_stop
            ]
        );
    }

}
