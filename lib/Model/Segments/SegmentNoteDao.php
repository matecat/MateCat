<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
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
     * Read the notes of a set of segments, such as one page of the segment analysis API.
     *
     * Not cached. Each call asks for a different list of ids, so a cached result was almost never
     * read again, and every list landed in the same Redis hash: on production that hash reached
     * 18.9 GB and pushed the login tokens out of Redis. The query uses the `id_segment` index.
     *
     * Example: `getBySegmentIds([10, 11])` returns every note of segments 10 and 11.
     *
     * @param list<int> $ids
     *
     * @return list<SegmentNoteStruct>
     * @throws PDOException
     */
    public function getBySegmentIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $stmt = $this->_getStatementForQuery(
            "SELECT * FROM segment_notes WHERE id_segment IN ( " . implode(',', array_fill(0, count($ids), '?')) . " ) "
        );
        $stmt->setFetchMode(PDO::FETCH_CLASS, SegmentNoteStruct::class);
        $stmt->execute(array_values($ids));

        $notes = [];
        foreach ($stmt->fetchAll() as $note) {
            if ($note instanceof SegmentNoteStruct) {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * @param int $start
     * @param int $stop
     *
     * @return array<int, list<array{id_segment: int, id: int, note: string}>>
     * @throws PDOException
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
     * @throws PDOException
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
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
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
