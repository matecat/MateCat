<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use Model\Database;
use PDO;
use ReflectionException;

class SegmentNoteDao extends AbstractDao {

    /**
     * @param int $id_segment
     * @param int $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws ReflectionException
     */
    public static function getBySegmentId( int $id_segment, int $ttl = 86400 ): array {

        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare( "SELECT * FROM segment_notes WHERE id_segment = ? " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt,
                SegmentNoteStruct::class,
                [ $id_segment ]
        );

    }

    /**
     * @param array $ids
     * @param int   $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws ReflectionException
     */
    public static function getBySegmentIds( array $ids = [], int $ttl = 86400 ): array {

        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare( "SELECT * FROM segment_notes WHERE id_segment IN ( " . implode( ', ', $ids ) . " ) " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt,
                SegmentNoteStruct::class,
                []
        );

    }

    /**
     * @param $start int start segment
     * @param $stop  int stop segment
     *
     * @return array array aggregated by id_segment
     */

    public static function getAggregatedBySegmentIdInInterval( $start, $stop ): array {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT id_segment, id, note FROM segment_notes " .
                " WHERE id_segment BETWEEN :start AND :stop AND json IS NULL"
        );
        $stmt->execute( [ 'start' => $start, 'stop' => $stop ] );

        return $stmt->fetchAll( PDO::FETCH_GROUP | PDO::FETCH_ASSOC );

    }

    /**
     * @param int $start
     * @param int $stop
     *
     * @return array
     */
    public static function getAllAggregatedBySegmentIdInInterval( int $start, int $stop ): array {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT id_segment, id, note, json FROM segment_notes " .
                " WHERE id_segment BETWEEN :start AND :stop"
        );
        $stmt->execute( [ 'start' => $start, 'stop' => $stop ] );

        return $stmt->fetchAll( PDO::FETCH_GROUP | PDO::FETCH_ASSOC );
    }


    /**
     * @param int $id_segment_start
     * @param int $id_segment_stop
     * @param int $ttl
     *
     * @return SegmentNoteStruct[]
     * @throws ReflectionException
     */
    public static function getJsonNotesByRange( int $id_segment_start, int $id_segment_stop, int $ttl = 0 ): array {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( "SELECT id_segment, json FROM segment_notes WHERE id_segment BETWEEN :start AND :stop AND note IS NULL " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap(
                $stmt, SegmentNoteStruct::class,
                [
                        'start' => $id_segment_start,
                        'stop'  => $id_segment_stop
                ] );

    }

    protected function _buildResult( array $array_result ) {

    }

}

