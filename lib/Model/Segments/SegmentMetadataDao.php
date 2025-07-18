<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use ReflectionException;

class SegmentMetadataDao extends AbstractDao {

    /**
     * get all meta
     *
     * @param int $id_segment
     * @param int $ttl
     *
     * NOTE: 604800 sec = 1 week
     *
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     */
    public static function getAll( int $id_segment, int $ttl = 604800 ): array {

        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare( "SELECT * FROM segment_metadata WHERE id_segment = ? " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt,
                SegmentMetadataStruct::class,
                [ $id_segment ]
        );
    }

    /**
     * @param array  $ids
     * @param string $key
     * @param int    $ttl
     *
     * @return SegmentMetadataStruct[]
     * @throws ReflectionException
     */
    public static function getBySegmentIds( array $ids, string $key, int $ttl = 604800 ): array {

        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare( "SELECT * FROM segment_metadata WHERE id_segment IN (" . implode( ', ', $ids ) . ") and meta_key = ? " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt,
                SegmentMetadataStruct::class,
                [ $key ]
        );
    }

    /**
     * get key
     *
     * @param int    $id_segment
     * @param string $key
     * @param int    $ttl
     *
     * NOTE: 604800 sec = 1 week
     *
     * @return array
     * @throws ReflectionException
     */
    public static function get( int $id_segment, string $key, int $ttl = 604800 ): array {

        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare( "SELECT * FROM segment_metadata WHERE id_segment = ? and meta_key = ? " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt,
                SegmentMetadataStruct::class,
                [ $id_segment, $key ]
        );
    }

    /**
     * @param SegmentMetadataStruct $metadataStruct
     */
    public static function save( SegmentMetadataStruct $metadataStruct ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "INSERT INTO segment_metadata " .
                " ( id_segment, meta_key, meta_value  ) VALUES " .
                " ( :id_segment, :key, :value ) "
        );

        $stmt->execute( [
                'id_segment' => $metadataStruct->id_segment,
                'key'        => $metadataStruct->meta_key,
                'value'      => $metadataStruct->meta_value,
        ] );
    }
}