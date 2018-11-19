<?php

class Segments_SegmentDao extends DataAccess_AbstractDao {
    const TABLE = 'segments' ;
    protected static $auto_increment_field = ['id'];

    public function countByFile( Files_FileStruct $file ) {
        $conn = $this->con->getConnection();
        $sql = "SELECT COUNT(1) FROM segments WHERE id_file = :id_file " ;

        $stmt = $conn->prepare( $sql ) ;
        $stmt->execute( array( 'id_file' => $file->id ) ) ;
        return (int) $stmt->fetch()[0] ;
    }

    /**
     * Returns an array of segments for the given file.
     * In order to limit the amount of memory, this method accepts an array of
     * columns to be returned.
     *
     * @param $id_file
     * @param $fields_list array
     *
     * @return Segments_SegmentStruct[]
     */
    public function getByFileId( $id_file, $fields_list = array() ) {
        $conn = $this->con->getConnection();

        if ( empty( $fields_list ) ) {
            $fields_list[] = '*' ;
        }

        $sql = " SELECT " . implode(', ', $fields_list ) . " FROM segments WHERE id_file = :id_file " ;
        $stmt = $conn->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );
        $stmt->execute( array( 'id_file' => $id_file ) ) ;

        return $stmt->fetchAll() ;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     * @return mixed
     */
    function countByChunk( Chunks_ChunkStruct $chunk) {
        $conn = $this->con->getConnection();
        $query = "SELECT COUNT(1) FROM segments s
            JOIN segment_translations st ON s.id = st.id_segment
            JOIN jobs ON st.id_job = jobs.id
            WHERE jobs.id = :id_job
            AND jobs.password = :password
            AND s.show_in_cattool ;
            "  ;
        $stmt = $conn->prepare( $query ) ;
        $stmt->execute( array( 'id_job' => $chunk->id, 'password' => $chunk->password ) ) ;
        $result = $stmt->fetch() ;
        return (int) $result[ 0 ] ;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $id_segment
     * @return \Segments_SegmentStruct
     */
    function getByChunkIdAndSegmentId( $id_job, $password, $id_segment) {
        $conn = $this->con->getConnection();

        $query = " SELECT segments.* FROM segments " .
                " INNER JOIN files_job fj USING (id_file) " .
                " INNER JOIN jobs ON jobs.id = fj.id_job " .
                " INNER JOIN files f ON f.id = fj.id_file " .
                " WHERE jobs.id = :id_job AND jobs.password = :password" .
                " AND segments.id_file = f.id " .
                " AND segments.id = :id_segment " ;

        $stmt = $conn->prepare( $query );

        $stmt->execute( array(
                'id_job'   => $id_job,
                'password' => $password,
                'id_segment'=> $id_segment
        ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return Segments_SegmentStruct[]
     */
    function getByChunkId( $id_job, $password ) {
        $conn = $this->con->getConnection();

        $query = "SELECT segments.* FROM segments
                 INNER JOIN files_job fj USING (id_file)
                 INNER JOIN jobs ON jobs.id = fj.id_job
                 AND jobs.id = :id_job AND jobs.password = :password
                 INNER JOIN files f ON f.id = fj.id_file
                 WHERE jobs.id = :id_job AND jobs.password = :password
                 AND segments.id_file = f.id
                 AND segments.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment
                 ";

        $stmt = $conn->prepare( $query );

        $stmt->execute( array(
                'id_job'   => $id_job,
                'password' => $password
        ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_segment
     *
     * @return Segments_SegmentStruct
     */
    public function getById( $id_segment ) {
        $conn = $this->con->getConnection();

        $query = "select * from segments where id = :id";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( array( 'id' => (int)$id_segment ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    /**
     * @param $id_list
     *
     * @return object
     */
    public function getContextAndSegmentByIDs( $id_list ){
        $query = "SELECT id, segment FROM segments WHERE id IN( :id_before, :id_segment, :id_after ) ORDER BY id ASC";
        $stmt = $this->_getStatementForCache( $query );
        /** @var $res Segments_SegmentStruct[] */
        $res = $this->_fetchObject( $stmt,
                new Segments_SegmentStruct(),
                $id_list
        );
        $reverse_id_list = array_flip( $id_list );
        foreach( $res as $element ){
            $id_list[ $reverse_id_list[ $element->id ] ] = $element;
        }
        return (object)$id_list;
    }

    /**
     * @param        $jid
     * @param        $password
     * @param int    $step
     * @param        $ref_segment
     * @param string $where
     *
     * @return array
     * @throws Exception
     */
    public function getSegmentsIdForQR( $jid, $password, $step = 10, $ref_segment, $where = "after", $options = [] ) {

        $db = Database::obtain()->getConnection();

        $options_conditions_query  = "";
        $options_join_query        = "";
        $options_conditions_values = [];
        $statuses = array_merge(
                Constants_TranslationStatus::$INITIAL_STATUSES,
                Constants_TranslationStatus::$TRANSLATION_STATUSES,
                Constants_TranslationStatus::$REVISION_STATUSES
        );
        if ( isset( $options[ 'filter' ][ 'status' ] ) && in_array($options[ 'filter' ][ 'status' ], $statuses) ) {
            $options_conditions_query              .= " AND st.status = :status ";
            $options_conditions_values[ 'status' ] = $options[ 'filter' ][ 'status' ];
        }

        if ( (isset( $options[ 'filter' ][ 'issue_category' ] ) && $options[ 'filter' ][ 'issue_category' ] != '' ) OR (isset( $options[ 'filter' ][ 'severity' ] ) &&  $options[ 'filter' ][ 'severity' ] != '') ) {

            $options_join_query .= " LEFT JOIN qa_entries e ON e.id_segment = st.id_segment AND e.id_job = st.id_job ";
            $options_join_query .= " LEFT JOIN segment_revisions sr ON sr.id_segment = st.id_segment ";

            if ( isset( $options[ 'filter' ][ 'issue_category' ] ) && $options[ 'filter' ][ 'issue_category' ] != '' ) {
                if ( in_array( $options[ 'filter' ][ 'issue_category' ], Constants_Revise::$categoriesDbNames ) ) {
                    $options_conditions_query .= " AND (sr." . $options[ 'filter' ][ 'issue_category' ] . " != '' AND sr." . $options[ 'filter' ][ 'issue_category' ] . " != 'none')";
                } else {
                    $options_conditions_query .= " AND e.id_category = :id_category ";
                    $options_conditions_values[ 'id_category' ] = $options[ 'filter' ][ 'issue_category' ];
                }

            }

            if ( isset( $options[ 'filter' ][ 'severity' ] ) && $options[ 'filter' ][ 'severity' ] != '' ) {
                $options_conditions_query                .= " AND (e.severity = :severity OR 
            (sr.err_typing = :severity OR sr.err_translation OR sr.err_terminology = :severity OR sr.err_language = :severity OR sr.err_style = :severity)) ";
                $options_conditions_values[ 'severity' ] = $options[ 'filter' ][ 'severity' ];
            }

        }


        $queryAfter = "
                SELECT * FROM (
                    SELECT distinct(s.id) AS __sid
                    FROM segments s
                    JOIN segment_translations st ON s.id = st.id_segment
                    JOIN jobs j ON j.id = st.id_job
                    %s 
                    WHERE st.id_job = :id_job
                        AND j.password = :password
                        AND s.show_in_cattool = 1
                        AND s.id > :ref_segment
                        AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
                        %s
                    LIMIT %u
                ) AS TT1";

        $queryBefore = "
                SELECT * from(
                    SELECT distinct(s.id) AS __sid
                    FROM segments s
                    JOIN segment_translations st ON s.id = st.id_segment
                    JOIN jobs j ON j.id = st.id_job
                    %s
                    WHERE st.id_job = :id_job
                        AND j.password = :password
                        AND s.show_in_cattool = 1
                        AND s.id < :ref_segment
                        AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
                        %s
                    ORDER BY __sid DESC
                    LIMIT %u
                ) as TT2";

        /*
         * This query is an union of the last two queries with only one difference:
         * the queryAfter parts differs for the equal sign.
         * Here is needed
         *
         */
        $queryCenter = "
                  SELECT * FROM ( 
                        SELECT distinct(s.id) AS __sid
                        FROM segments s
                        JOIN segment_translations st ON s.id = st.id_segment
                        JOIN jobs j ON j.id = st.id_job
                        %s
                        WHERE st.id_job = :id_job
                            AND j.password = :password
                            AND s.show_in_cattool = 1
                            AND s.id >= :ref_segment
                            %s
                        LIMIT %u 
                  ) AS TT1
                  UNION
                  SELECT * from(
                        SELECT distinct(s.id) AS __sid
                        FROM segments s
                        JOIN segment_translations st ON s.id = st.id_segment
                        JOIN jobs j ON j.id = st.id_job
                        %s
                        WHERE st.id_job = :id_job
                            AND j.password = :password
                            AND s.show_in_cattool = 1
                            AND s.id < :ref_segment
                            %s
                        ORDER BY __sid DESC
                        LIMIT %u
                  ) AS TT2";

        switch ( $where ) {
            case 'after':
                $subQuery = sprintf( $queryAfter, $options_join_query, $options_conditions_query, $step * 2 );
                break;
            case 'before':
                $subQuery = sprintf( $queryBefore, $options_join_query, $options_conditions_query, $step * 2 );
                break;
            case 'center':
                $subQuery = sprintf( $queryCenter, $options_join_query, $options_conditions_query, $step, $options_join_query, $options_conditions_query, $step );
                break;
            default:
                throw new Exception( "No direction selected" );
                break;
        }

        $stmt = $db->prepare( $subQuery );
        $conditions_values = array_merge([ 'id_job' => $jid, 'password' => $password, 'ref_segment' => $ref_segment ], $options_conditions_values);
        $stmt->execute( $conditions_values );
        $segments_id = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $segments_id = array_map( function ( $segment_row ) {
            return $segment_row[ '__sid' ];
        }, $segments_id );

        return $segments_id;
    }

    /**
     * @param $segments_id
     * @param $job_id
     * @param $job_password
     *
     * @return \QualityReport_QualityReportSegmentStruct[]
     */

    public function getSegmentsForQr($segments_id, $job_id, $job_password){
        $db = Database::obtain()->getConnection();

        $prepare_str_segments_id = str_repeat( 'UNION SELECT ? ', count( $segments_id ) - 1);

        $query = "SELECT 
                s.id AS sid,
                s.segment,
                j.target,
                fj.id_file,
                f.filename,
                s.raw_word_count,
                IF (st.status='NEW',NULL,st.translation) AS translation,
                UNIX_TIMESTAMP(st.translation_date) AS version,
                IF( st.locked AND match_type = 'ICE', 1, 0 ) AS ice_locked,
                st.status,
                COALESCE(time_to_edit, 0) AS time_to_edit,
                st.warning,
                st.suggestion_match as suggestion_match,
                st.suggestion_source,
                st.suggestion,
                st.edit_distance,
                st.locked,
                st.match_type,
                st.version_number
                
                FROM segments s
                RIGHT JOIN segment_translations st ON st.id_segment = s.id
                RIGHT JOIN jobs j ON j.id = st.id_job
                RIGHT JOIN files_job fj ON fj.id_job = j.id
                RIGHT JOIN files f ON f.id = fj.id_file AND s.id_file = f.id
                JOIN (
                    SELECT ? as id_segment
                    ".$prepare_str_segments_id."
                 ) AS SLIST USING( id_segment )
                 WHERE j.id = ? AND j.password = ?
            ORDER BY sid ASC";

        $stmt = $db->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_CLASS, "\QualityReport_QualityReportSegmentStruct");
        $stmt->execute(array_merge($segments_id, array($job_id, $job_password)));

        $results = $stmt->fetchAll();

        return $results;
    }

    /**
     * @param Segments_SegmentStruct[] $obj_arr
     *
     * @throws Exception
     */
    public function createList( Array $obj_arr ) {

        $obj_arr = array_chunk( $obj_arr, 100 );

        $baseQuery = "INSERT INTO segments ( 
                            id, 
                            internal_id, 
                            id_file,
                            /* id_project, */ 
                            segment, 
                            segment_hash, 
                            raw_word_count, 
                            xliff_mrk_id, 
                            xliff_ext_prec_tags, 
                            xliff_ext_succ_tags, 
                            show_in_cattool,
                            xliff_mrk_ext_prec_tags,
                            xliff_mrk_ext_succ_tags
                            ) VALUES ";


        Log::doLog( "Segments: Total Queries to execute: " . count( $obj_arr ) );

        $tuple_marks = "( " . rtrim( str_repeat( "?, ", 12 ), ", " ) . " )";  //set to 13 when implements id_project

        foreach ( $obj_arr as $i => $chunk ) {

            $query = $baseQuery . rtrim( str_repeat( $tuple_marks . ", ", count( $chunk ) ), ", " );

            $values = [];
            foreach( $chunk as $segStruct ){

                $values[] =$segStruct->id;
                $values[] =$segStruct->internal_id;
                $values[] =$segStruct->id_file;
                /* $values[] = $segStruct->id_project */
                $values[] = $segStruct->segment;
                $values[] = $segStruct->segment_hash;
                $values[] = $segStruct->raw_word_count;
                $values[] = $segStruct->xliff_mrk_id;
                $values[] = $segStruct->xliff_ext_prec_tags;
                $values[] = $segStruct->xliff_ext_succ_tags;
                $values[] = $segStruct->show_in_cattool;
                $values[] = $segStruct->xliff_mrk_ext_prec_tags;
                $values[] = $segStruct->xliff_mrk_ext_succ_tags;

            }

            try {

                $stm = $this->con->getConnection()->prepare( $query );
                $stm->execute( $values );
                Log::doLog( "Segments: Executed Query " . ( $i + 1 ) );

            } catch ( PDOException $e ) {
                Log::doLog( "Segment import - DB Error: " . $e->getMessage() . " - \n" );
                throw new Exception( "Segment import - DB Error: " . $e->getMessage() . " - $chunk", -2 );
            }

        }


    }

}
