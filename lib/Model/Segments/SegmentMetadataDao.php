<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDOException;
use ReflectionException;

class SegmentMetadataDao extends AbstractDao
{
    private static string $sql_get_all = "SELECT * FROM segment_metadata WHERE id_segment = ? ";
    private static string $sql_find_by_id_segment_and_key = "SELECT * FROM segment_metadata WHERE id_segment = ? and meta_key = ? ";
    const string _keymap_get_by_segment_ids   = "Model\\Segments\\SegmentMetadataDao::getBySegmentIds-";

    /**
     * get all meta
     *
     * @param int $id_segment
     * @param int $ttl
     *
     * NOTE: 604,800 sec = 1 week
     *
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     */
    public static function getAll(int $id_segment, int $ttl = 604800): array
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare(self::$sql_get_all);

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment]
        );
    }

    /**
     * Destroys the cached metadata for the specified segment.
     *
     * @param int $id_segment The ID of the segment whose cache needs to be destroyed.
     *
     * @return bool True if the cache was successfully destroyed, false otherwise.
     * @throws ReflectionException
     * @throws PDOException
     */
    public static function destroyGetAllCache(int $id_segment): bool
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$sql_get_all);

        return $thisDao->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment]);
    }

    /**
     * @param array $ids
     * @param string $key
     * @param int $ttl
     *
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     */
    public static function getBySegmentIds(array $ids, string $key, int $ttl = 604800): array
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare("SELECT * FROM segment_metadata WHERE id_segment IN (" . implode(', ', $ids) . ") and meta_key = ? ");

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$key]
        );
    }

    /**
     * get key
     *
     * @param int $id_segment
     * @param string $key
     * @param int $ttl
     *
     * NOTE: 604,800 sec = 1 week
     *
     * @return array
     * @throws ReflectionException
     */
    public static function get(int $id_segment, string $key, int $ttl = 604800): array
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare(self::$sql_find_by_id_segment_and_key);

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment, $key]
        );
    }

    /**
     * Destroy cache of segment metadata based on segment ID and key.
     *
     * @param int $id_segment The identifier of the segment to target.
     * @param string $key The key associated with the cache entry to be destroyed.
     *
     * @return bool True if the cache was successfully destroyed, false otherwise.
     * @throws PDOException
     * @throws ReflectionException
     */
    public static function destroyCache(int $id_segment, string $key): bool
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$sql_find_by_id_segment_and_key);

        return $thisDao->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment, $key]);
    }

    /**
     * @throws PDOException
     */
    public static function delete(int $id_segment, string $key): void
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("DELETE FROM segment_metadata WHERE id_segment = ? AND meta_key = ?");
        $stmt->execute([$id_segment, $key]);
    }

    /**
     * @param SegmentMetadataStruct $metadataStruct
     */
    public static function save(SegmentMetadataStruct $metadataStruct): void
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO segment_metadata " .
            " ( id_segment, meta_key, meta_value  ) VALUES " .
            " ( :id_segment, :key, :value ) "
        );

        $stmt->execute([
            'id_segment' => $metadataStruct->id_segment,
            'key' => $metadataStruct->meta_key,
            'value' => $metadataStruct->meta_value,
        ]);
    }

    /**
     * Destroy cache for getBySegmentIds queries matching the given meta_key.
     *
     * Because getBySegmentIds bakes segment IDs into the SQL string (not bind params),
     * we cannot reconstruct the exact cache key via _destroyObjectCache.
     * Instead, we delete the keyMap directly using _deleteCacheByKey.
     * @throws ReflectionException
     * @throws Exception
     */
    public static function destroyGetBySegmentIdsCache(string $key): bool
    {
        $thisDao  = new self();
        $keyMap   = self::_keymap_get_by_segment_ids . $key;

        return $thisDao->_deleteCacheByKey($keyMap, false);
    }

}
