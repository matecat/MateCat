<?php 

class Segments_SegmentNoteDao extends DataAccess_AbstractDao {

    public static function bulkInsertFromProjectStrucutre( $notes ) {

        Log::doLog( $notes ) ;

        $template = " INSERT INTO segment_notes ( id_segment, internal_id, note ) VALUES " ;

        $insert_values = array();
        $chunk_size = 30;

        foreach ( $notes as $internal_id => $v ) {
            $entries  = $v[ 'entries' ];
            $segments = $v[ 'segment_ids' ];

            foreach ( $segments as $id_segment ) {
                foreach ( $entries as $note ) {
                    $insert_values[] = array( $id_segment, $internal_id, $note );
                }
            }
        }

        $chunked = array_chunk( $insert_values, $chunk_size ) ;
        $conn = Database::obtain()->getConnection();

        foreach( $chunked as $chunk ) {
            $values_sql_array = array_fill( 0, count($chunk), " ( ?, ?, ?  ) " ) ;
            $stmt = $conn->prepare( $template . implode( ', ', $values_sql_array )) ;
            $flattened_values = array_reduce( $chunk, 'array_merge', array() );
            $stmt->execute( $flattened_values ) ;
        }

    }

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

