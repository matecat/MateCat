<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
use PDO;
use PDOException;
use ReflectionException;
use TypeError;

class SegmentMetadataDao extends AbstractDao
{
    const string TABLE = 'segment_metadata';
    const string _query_get_all = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? ";
    const string _query_get = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? and meta_key = ? ";

/**
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAll(int $id_segment, int $ttl = 86400): SegmentMetadataCollection
    {
        $stmt = $this->database->getConnection()->prepare(self::_query_get_all);

        $results = $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment]
        );

        return new SegmentMetadataCollection($results);
    }

    /**
     * Read one metadata key for a set of segments, such as one page of the segment analysis API.
     *
     * Not cached. Each call asks for a different list of ids, so a cached result was almost never
     * read again, and every list landed in the same Redis hash for that key, which grew to 896 MB
     * on production. The query uses the `(id_segment, meta_key)` index.
     *
     * Example: `getBySegmentIds([10, 11], 'id_request')` returns the `id_request` rows of segments
     * 10 and 11.
     *
     * @param int[] $ids
     *
     * @return list<SegmentMetadataStruct>
     * @throws PDOException
     */
    public function getBySegmentIds(array $ids, string $key): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->_getStatementForQuery("SELECT * FROM segment_metadata WHERE id_segment IN ($placeholders) and meta_key = ? ");
        $stmt->setFetchMode(PDO::FETCH_CLASS, SegmentMetadataStruct::class);
        $stmt->execute([...array_values($ids), $key]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row instanceof SegmentMetadataStruct) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(int $id_segment, string $key, int $ttl = 604800): ?SegmentMetadataStruct
    {
        $stmt = $this->database->getConnection()->prepare(self::_query_get);

        $results = $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment, $key]
        );

        return $results[0] ?? null;
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function delete(int $id_segment, string $key): void
    {
        $stmt = $this->database->getConnection()->prepare("DELETE FROM segment_metadata WHERE id_segment = ? AND meta_key = ?");
        $stmt->execute([$id_segment, $key]);

        $this->destroyCache($this->addressOf($id_segment, $key));
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function save(SegmentMetadataStruct $metadataStruct): void
    {
        $stmt = $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata " .
            " ( id_segment, meta_key, meta_value  ) VALUES " .
            " ( :id_segment, :key, :value ) "
        );

        $stmt->execute([
            'id_segment' => $metadataStruct->id_segment,
            'key' => $metadataStruct->meta_key,
            'value' => $metadataStruct->meta_value,
        ]);

        $this->destroyCache($metadataStruct);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function upsert(int $id_segment, string $key, string $value): void
    {
        $stmt = $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata " .
            " ( id_segment, meta_key, meta_value ) VALUES " .
            " ( :id_segment, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE meta_value = :value "
        );

        $stmt->execute([
            'id_segment' => $id_segment,
            'key' => $key,
            'value' => $value,
        ]);

        $this->destroyCache($this->addressOf($id_segment, $key));
    }

    /**
     * Evict every cached address one metadata row is read at: by segment, and by segment and key.
     * `getBySegmentIds()` and `getAllInRange()` are not cached, so they have nothing to evict.
     *
     * The struct has to name the segment and the key. Every write in this DAO goes through here, so
     * a caller never holds a list of evictions it can be one short of.
     *
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError when the struct names no segment or no key
     * @throws Exception
     */
    public function destroyCache(SegmentMetadataStruct $metadata): void
    {
        if (!isset($metadata->id_segment)) {
            throw new TypeError('A segment metadata cache eviction needs the id of the segment.');
        }

        if (!isset($metadata->meta_key)) {
            throw new TypeError('A segment metadata cache eviction needs the meta key of the row.');
        }

        $this->destroyCacheAll((int)$metadata->id_segment);
        $this->destroyCacheKey((int)$metadata->id_segment, $metadata->meta_key);
    }

    /** The struct a write holds only as a pair of values. */
    private function addressOf(int $id_segment, string $key): SegmentMetadataStruct
    {
        $address = new SegmentMetadataStruct();
        $address->id_segment = $id_segment;
        $address->meta_key = $key;

        return $address;
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    private function destroyCacheAll(int $id_segment): bool
    {
        $stmt = $this->database->getConnection()->prepare(self::_query_get_all);

        return $this->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment]);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    private function destroyCacheKey(int $id_segment, string $key): bool
    {
        $stmt = $this->database->getConnection()->prepare(self::_query_get);

        return $this->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment, $key]);
    }

    /**
     * Read every metadata row of a range of segments, grouped by segment, such as one page of the
     * editor in `GetSegmentsController`.
     *
     * Not cached. The rows decide whether a segment can be edited, and a cached copy can go stale:
     * a read that started before a disable/enable write commits can store the old rows after that
     * write's eviction. The query uses the `(id_segment, meta_key)` index.
     *
     * Example: `getAllInRange(5000, 5200)` returns `[5003 => SegmentMetadataCollection, ...]`, one
     * entry per segment that has metadata.
     *
     * @return array<int, SegmentMetadataCollection>
     * @throws PDOException
     */
    public function getAllInRange(int $startSid, int $stopSid): array
    {
        $stmt = $this->_getStatementForQuery(
            "SELECT * FROM " . self::TABLE . " WHERE id_segment BETWEEN ? AND ? ORDER BY id_segment"
        );
        $stmt->setFetchMode(PDO::FETCH_CLASS, SegmentMetadataStruct::class);
        $stmt->execute([$startSid, $stopSid]);

        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row instanceof SegmentMetadataStruct) {
                $results[] = $row;
            }
        }

        $grouped = [];
        foreach ($results as $struct) {
            $grouped[$struct->id_segment][] = $struct;
        }

        return array_map(
            static fn(array $structs) => new SegmentMetadataCollection($structs),
            $grouped
        );
    }

}
