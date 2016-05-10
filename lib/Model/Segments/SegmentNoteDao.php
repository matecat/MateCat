<?php 

class Segments_SegmentNoteDao extends DataAccess_AbstractDao {

    /**
     * @param $id_segment
     *
     * @return Segments_SegmentNoteStruct[]
     */
    public static function getBySegmentId( $id_segment ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM segment_notes " .
            " WHERE id_segment = ? " ); 
        $stmt->execute( array( $id_segment ) ); 
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Segments_SegmentNoteStruct');
        return $stmt->fetchAll(); 
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
     * @param $start start segment
     * @param $stop stop segment
     * @return array array aggregated by id_segment
     */

    public static function getAggregatedBySegmentIdInInterval($start, $stop) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT id_segment, id, note FROM segment_notes " .
            " WHERE id_segment BETWEEN :start AND :stop "
        );
        $stmt->execute( array( 'start' => $start, 'stop' => $stop ) );

        return $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    }

    protected function _buildResult( $array_result ) {

    }

}

