<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use ReflectionException;

class SegmentMetadataDao extends AbstractDao
{
    private static string $sql_get_all = "SELECT * FROM segment_metadata WHERE id_segment = ? ";
    private static string $sql_find_by_id_segment_and_key = "SELECT * FROM segment_metadata WHERE id_segment = ? and meta_key = ? ";
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
     */
    public static function destroyCache(int $id_segment, string $key): bool
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$sql_find_by_id_segment_and_key);

        return $thisDao->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment, $key]);
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
     * Disable translation for a specific segment.
     *
     * @param int $id_job The ID of the job associated with the segment.
     * @param int $id_segment The ID of the segment for which translation will be disabled.
     *
     * @return void
     */
    public static function setTranslationDisabled(int $id_job, int $id_segment): void
    {
        $metadata = new SegmentMetadataStruct();
        $metadata->id_segment = $id_segment;
        $metadata->meta_key = 'translation_disabled';
        $metadata->meta_value = 1;

        SegmentMetadataDao::save($metadata);

        $cacheKey = 'segment_is_disabled_' . $id_job . '_' . $id_segment;
        $cachedQuery = "__SEGMENT_IS_DISABLED__" . $id_job . "_" . $id_segment . "";

        $thisDao = new self();
        $thisDao->_setInCacheMap($cacheKey, $cachedQuery, [1]);
    }
}