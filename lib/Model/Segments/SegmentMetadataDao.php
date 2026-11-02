<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
use PDOException;
use ReflectionException;
use TypeError;

class SegmentMetadataDao extends AbstractDao
{
    const string TABLE = 'segment_metadata';
    const string _query_get_all = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? ";
    const string _query_get = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? and meta_key = ? ";
    const string _keymap_get_by_segment_ids = "Model\\Segments\\SegmentMetadataDao::getBySegmentIds-";
    const string _keymap_get_all_in_range = "Model\\Segments\\SegmentMetadataDao::getAllInRange-";

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
     * Cached under one hash per key and project, so a write evicts only its own project's reads.
     *
     * @param int[] $ids
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function getBySegmentIds(int $id_project, array $ids, string $key, int $ttl = 86400): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->database->getConnection()->prepare("SELECT * FROM segment_metadata WHERE id_segment IN ($placeholders) and meta_key = ? ");

        return $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [...array_values($ids), $key],
            self::_keymap_get_by_segment_ids . $key . '-' . $id_project
        );
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
    public function delete(int $id_segment, string $key, int $id_project): void
    {
        $stmt = $this->database->getConnection()->prepare("DELETE FROM segment_metadata WHERE id_segment = ? AND meta_key = ?");
        $stmt->execute([$id_segment, $key]);

        $this->destroyCache($this->addressOf($id_segment, $key), $id_project);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function save(SegmentMetadataStruct $metadataStruct, int $id_project): void
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

        $this->destroyCache($metadataStruct, $id_project);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function upsert(int $id_segment, string $key, string $value, int $id_project): void
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

        $this->destroyCache($this->addressOf($id_segment, $key), $id_project);
    }

    /**
     * Evict every address one metadata row is read at: by segment, by segment and key, by that key
     * across a set of segments, and across a segment range.
     *
     * The struct has to name the segment and the key. Every write in this DAO goes through here, so
     * a caller never holds a list of evictions it can be one short of.
     *
     * Unlike other doors, this one also takes the project: the set and range reads are cached per
     * project, and a metadata row carries no project column to derive it from. Looking it up here
     * would cost a join on every write, and every writer already holds the project.
     *
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError when the struct names no segment or no key
     * @throws Exception
     */
    public function destroyCache(SegmentMetadataStruct $metadata, int $id_project): void
    {
        if (!isset($metadata->id_segment)) {
            throw new TypeError('A segment metadata cache eviction needs the id of the segment.');
        }

        if (!isset($metadata->meta_key)) {
            throw new TypeError('A segment metadata cache eviction needs the meta key of the row.');
        }

        $this->destroyCacheAll((int)$metadata->id_segment);
        $this->destroyCacheKey((int)$metadata->id_segment, $metadata->meta_key);
        $this->destroyCacheBySegmentIds($metadata->meta_key, $id_project);
        $this->destroyCacheAllInRange($id_project);
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
     * @throws ReflectionException
     * @throws Exception
     */
    private function destroyCacheBySegmentIds(string $key, int $id_project): bool
    {
        $keyMap = self::_keymap_get_by_segment_ids . $key . '-' . $id_project;

        return $this->_deleteCacheByKey($keyMap, false);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function destroyCacheAllInRange(int $id_project): bool
    {
        return $this->_deleteCacheByKey(self::_keymap_get_all_in_range . $id_project, false);
    }

    /**
     * Cached under one hash per project, which every write in that project deletes.
     *
     * The query does not filter by project: segment ids are reserved file by file, so a range can
     * hold another project's segments. Those rows are not evicted by their own project's writes and
     * may be stale; a caller uses only the ids of its own segments.
     *
     * @return array<int, SegmentMetadataCollection>
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAllInRange(int $id_project, int $startSid, int $stopSid, int $ttl = 86400): array
    {
        $conn = $this->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE id_segment BETWEEN ? AND ? ORDER BY id_segment"
        );

        /** @var SegmentMetadataStruct[] $results */
        $results = $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$startSid, $stopSid],
            self::_keymap_get_all_in_range . $id_project
        );

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
