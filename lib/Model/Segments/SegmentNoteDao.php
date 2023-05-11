<?php 

class Segments_SegmentNoteDao extends DataAccess_AbstractDao {

    /**
     * @param     $id_segment
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|Segments_SegmentNoteStruct[]
     */
    public static function getBySegmentId( $id_segment, $ttl = 86400 ) {

        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare( "SELECT * FROM segment_notes WHERE id_segment = ? " );
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt,
                new Segments_SegmentNoteStruct(),
                [ $id_segment ]
        );

    }

    /**
     * @param array $ids
     * @param int $ttl
     * @return DataAccess_IDaoStruct[]
     */
    public static function getBySegmentIds(array $ids = [], $ttl = 86400){

        $thisDao = new self();
        $conn = $thisDao->getDatabaseHandler();
        $stmt = $conn->getConnection()->prepare( "SELECT * FROM segment_notes WHERE id_segment IN ( " . implode(', ' , $ids ) . " ) " );
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt,
            new Segments_SegmentNoteStruct(),
            []
        );
    }

    public static function insertRecord( $values ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "INSERT INTO segment_notes " . 
            " ( id_segment, internal_id, note ) VALUES " .
            " ( :id_segment, :internal_id, :note ) "
        ); 

        $stmt->execute( $values ); 
    }

    /**
     * @param $start int start segment
     * @param $stop int stop segment
     * @return array array aggregated by id_segment
     */

    public static function getAggregatedBySegmentIdInInterval($start, $stop) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT id_segment, id, note FROM segment_notes " .
            " WHERE id_segment BETWEEN :start AND :stop AND json IS NULL"
        );
        $stmt->execute( array( 'start' => $start, 'stop' => $stop ) );

        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    }

    public static function getAllAggregatedBySegmentIdInInterval($start, $stop) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT id_segment, id, note, json FROM segment_notes " .
                " WHERE id_segment BETWEEN :start AND :stop"
        );
        $stmt->execute( array( 'start' => $start, 'stop' => $stop ) );

        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }


    /**
     * @param     $id_segment_start
     * @param     $id_segment_stop
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|Segments_SegmentNoteStruct[]
     */
    public static function getJsonNotesByRange( $id_segment_start, $id_segment_stop, $ttl = 0 ){

        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT id_segment, json FROM segment_notes WHERE id_segment BETWEEN :start AND :stop AND note IS NULL " );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject(
                $stmt, new Segments_SegmentNoteStruct(),
                [
                        'start' => $id_segment_start,
                        'stop'  => $id_segment_stop
                ] );

    }

    protected function _buildResult( $array_result ) {

    }

}

