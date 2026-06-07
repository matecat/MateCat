<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
use PDOException;
use ReflectionException;

class SegmentMetadataDao extends AbstractDao
{
    const string TABLE = 'segment_metadata';
    const string _query_get_all = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? ";
    const string _query_get = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? and meta_key = ? ";
    const string _keymap_get_by_segment_ids = "Model\\Segments\\SegmentMetadataDao::getBySegmentIds-";

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
     * @param int[] $ids
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function getBySegmentIds(array $ids, string $key, int $ttl = 86400): array
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->database->getConnection()->prepare("SELECT * FROM segment_metadata WHERE id_segment IN ($placeholders) and meta_key = ? ");

        return $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [...array_values($ids), $key]
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
     * @throws PDOException
     */
    public function delete(int $id_segment, string $key): void
    {
        $stmt = $this->database->getConnection()->prepare("DELETE FROM segment_metadata WHERE id_segment = ? AND meta_key = ?");
        $stmt->execute([$id_segment, $key]);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
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

        $this->destroyGetAllCache($metadataStruct->id_segment);
        $this->destroyGetCache($metadataStruct->id_segment, $metadataStruct->meta_key);
        $this->destroyGetBySegmentIdsCache($metadataStruct->meta_key);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
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

        $this->destroyGetAllCache($id_segment);
        $this->destroyGetCache($id_segment, $key);
        $this->destroyGetBySegmentIdsCache($key);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function destroyGetAllCache(int $id_segment): bool
    {
        $stmt = $this->database->getConnection()->prepare(self::_query_get_all);

        return $this->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment]);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function destroyGetCache(int $id_segment, string $key): bool
    {
        $stmt = $this->database->getConnection()->prepare(self::_query_get);

        return $this->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment, $key]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function destroyGetBySegmentIdsCache(string $key): bool
    {
        $keyMap = self::_keymap_get_by_segment_ids . $key;

        return $this->_deleteCacheByKey($keyMap, false);
    }

    /**
     * @return array<int, SegmentMetadataCollection>
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAllInRange(int $startSid, int $stopSid, int $ttl = 86400): array
    {
        $conn = $this->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE . " WHERE id_segment BETWEEN ? AND ? ORDER BY id_segment"
        );

        /** @var SegmentMetadataStruct[] $results */
        $results = $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$startSid, $stopSid]
        );

        $grouped = [];
        foreach ($results as $struct) {
            $grouped[(int)$struct->id_segment][] = $struct;
        }

        return array_map(
            static fn(array $structs) => new SegmentMetadataCollection($structs),
            $grouped
        );
    }

}
