<?php 

class Segments_SegmentNoteDao extends DataAccess_AbstractDao {

    public static function bulkInsertFromProjectStructure( $notes ) {
        $template = " INSERT INTO segment_notes ( id_segment, internal_id, note, json ) VALUES " ;

        $insert_values = array();
        $chunk_size = 30;

        foreach ( $notes as $internal_id => $v ) {

            $entries  = $v[ 'entries' ];
            $segments = $v[ 'segment_ids' ];

            $json_entries  = $v[ 'json' ];
            $json_segment_ids = $v[ 'json_segment_ids' ];

            foreach ( $segments as $id_segment ) {
                foreach ( $entries as $note ) {
                    $insert_values[] = array( $id_segment, $internal_id, $note, null );
                }
            }

            foreach ( $json_segment_ids as $id_segment ) {
                foreach ( $json_entries as $json ) {
                    $insert_values[] = array( $id_segment, $internal_id, null, $json );
                }
            }

        }

        $chunked = array_chunk( $insert_values, $chunk_size ) ;
        $conn = Database::obtain()->getConnection();

        foreach( $chunked as $chunk ) {
            $values_sql_array = array_fill( 0, count($chunk), " ( ?, ?, ?, ? ) " ) ;
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
        $stmt = $conn->prepare( "SELECT id_segment, json FROM segment_notes WHERE id_segment BETWEEN :start AND :stop AND note = '' " );

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

