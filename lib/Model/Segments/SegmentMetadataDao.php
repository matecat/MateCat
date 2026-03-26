<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use ReflectionException;

class SegmentMetadataDao extends AbstractDao
{

    /**
     * Get all metadata for a segment.
     *
     * @param int $id_segment
     * @param int $ttl Cache TTL in seconds (default: 1 week)
     *
     * @return SegmentMetadataCollection
     * @throws ReflectionException
     */
    public static function getAll(int $id_segment, int $ttl = 604800): SegmentMetadataCollection
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare("SELECT * FROM segment_metadata WHERE id_segment = ? ");

        $results = $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment]
        );

        return new SegmentMetadataCollection($results);
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
     * Get a single metadata entry by segment ID and key.
     *
     * @param int $id_segment
     * @param string $key
     * @param int $ttl Cache TTL in seconds (default: 1 week)
     *
     * @return SegmentMetadataStruct|null
     * @throws ReflectionException
     */
    public static function get(int $id_segment, string $key, int $ttl = 604800): ?SegmentMetadataStruct
    {
        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare("SELECT * FROM segment_metadata WHERE id_segment = ? and meta_key = ? ");

        $results = $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            SegmentMetadataStruct::class,
            [$id_segment, $key]
        );

        return $results[0] ?? null;
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
}
