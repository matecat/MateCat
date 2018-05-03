<?php 

class Segments_SegmentNoteDao extends DataAccess_AbstractDao {

    /**
     * @param $notes
     *
     * @throws Exception
     */
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
        Log::doLog( "Notes: Total Rows to insert: " . count( $chunked ) );

        $conn = Database::obtain()->getConnection();

        try {

            foreach( $chunked as $i => $chunk ) {
                $values_sql_array = array_fill( 0, count($chunk), " ( ?, ?, ?, ? ) " ) ;
                $stmt = $conn->prepare( $template . implode( ', ', $values_sql_array )) ;
                $flattened_values = array_reduce( $chunk, 'array_merge', array() );
                $stmt->execute( $flattened_values ) ;
                Log::doLog( "Notes: Executed Query " . ( $i + 1 ) );
            }

        } catch ( Exception $e ){
            Log::doLog( "Notes import - DB Error: " . $e->getMessage() . " - \n" );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doLog( "Notes import - Statement: " . $stmt->queryString . "\n" );
            Log::doLog( "Notes Chunk Dump: " . var_export( $chunk , true ) . "\n" );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doLog( "Notes Flattened Values Dump: " . var_export( $flattened_values , true ) . "\n" );
            throw new Exception( "Notes import - DB Error: " . $e->getMessage(), 0 , $e );
        }

    }

    /**
     * @param     $id_segment
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|Segments_SegmentNoteStruct[]
     */
    public static function getBySegmentId( $id_segment, $ttl = 86400 ) {

        $thisDao = new self();
        $conn = $thisDao->getConnection();
        $stmt = $conn->getConnection()->prepare( "SELECT * FROM segment_notes WHERE id_segment = ? " );
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt,
                new Segments_SegmentNoteStruct(),
                [ $id_segment ]
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

