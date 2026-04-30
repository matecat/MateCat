<?php

namespace Model\Segments;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDOException;
use ReflectionException;

class SegmentMetadataDao extends AbstractDao
{
    const string TABLE                        = 'segment_metadata';
    const string _query_get_all               = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? ";
    const string _query_get                   = "SELECT * FROM " . self::TABLE . " WHERE id_segment = ? and meta_key = ? ";
    const string _keymap_get_by_segment_ids   = "Model\\Segments\\SegmentMetadataDao::getBySegmentIds-";

    /**
     * Get all metadata for a segment.
     *
     * @param int $id_segment
     * @param int $ttl Cache TTL in seconds (default: 1 week)
     *
     * @return SegmentMetadataCollection
     * @throws ReflectionException
     * @throws Exception
     */
    public static function getAll(int $id_segment, int $ttl = 86400): SegmentMetadataCollection
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare(self::_query_get_all);

        $results = $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment]
        );

        return new SegmentMetadataCollection($results);
    }

    /**
     * @param int[] $ids
     * @param string $key
     * @param int $ttl
     *
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public static function getBySegmentIds(array $ids, string $key, int $ttl = 86400): array
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
     * Get a single metadata entry by segment ID and key.
     *
     * @param int $id_segment
     * @param string $key
     * @param int $ttl Cache TTL in seconds (default: 1 week)
     *
     * @return SegmentMetadataStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    public static function get(int $id_segment, string $key, int $ttl = 604800): ?SegmentMetadataStruct
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare(self::_query_get);

        $results = $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment, $key]
        );

        return $results[0] ?? null;
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
     * @throws ReflectionException
     * @throws PDOException
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

        self::destroyGetAllCache($metadataStruct->id_segment);
        self::destroyGetCache($metadataStruct->id_segment, $metadataStruct->meta_key);
        self::destroyGetBySegmentIdsCache($metadataStruct->meta_key);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public static function upsert(int $id_segment, string $key, string $value): void
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO segment_metadata " .
            " ( id_segment, meta_key, meta_value ) VALUES " .
            " ( :id_segment, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE meta_value = :value "
        );

        $stmt->execute([
            'id_segment' => $id_segment,
            'key'        => $key,
            'value'      => $value,
        ]);

        self::destroyGetAllCache($id_segment);
        self::destroyGetCache($id_segment, $key);
        self::destroyGetBySegmentIdsCache($key);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public static function destroyGetAllCache(int $id_segment): bool
    {
        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare(self::_query_get_all);

        return $thisDao->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment]);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public static function destroyGetCache(int $id_segment, string $key): bool
    {
        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare(self::_query_get);

        return $thisDao->_destroyObjectCache($stmt, SegmentMetadataStruct::class, [$id_segment, $key]);
    }

    /**
     * Disable translation for a specific segment.
     *
     * @param int $id_segment The ID of the segment for which translation will be disabled.
     *
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     */
    public static function setTranslationDisabled(int $id_segment): void
    {
        $metadata = new SegmentMetadataStruct();
        $metadata->id_segment = $id_segment;
        $metadata->meta_key = SegmentMetadataMarshaller::TRANSLATION_DISABLED->value;
        $metadata->meta_value = "1";

        SegmentMetadataDao::save($metadata);
    }

    /**
     * Destroy cache for getBySegmentIds queries matching the given meta_key.
     *
     * Because getBySegmentIds bakes segment IDs into the SQL string (not bind params),
     * we cannot reconstruct the exact cache key via _destroyObjectCache.
     * Instead, we delete the keyMap directly using _deleteCacheByKey.
     * @throws ReflectionException
     */
    public static function destroyGetBySegmentIdsCache(string $key): bool
    {
        $thisDao  = new self();
        $keyMap   = self::_keymap_get_by_segment_ids . $key;

        return $thisDao->_deleteCacheByKey($keyMap, false);
    }
}
