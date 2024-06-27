<?php

use DataAccess\ShapelessConcreteStruct;
use Features\ReviewExtended\ReviewUtils;
use Segments\SegmentUIStruct;

class Segments_SegmentDao extends DataAccess_AbstractDao {
    const TABLE = 'segments';
    protected static $auto_increment_field = [ 'id' ];


    const ISSUE_CATEGORY_ALL = 'all';

    protected static $queryForGlobalMismatches = " SELECT id_segment, id_job , segment_hash, translation 
                         FROM segment_translations 
                         WHERE id_job = :id_job
                         AND segment_translations.status IN( :st_translated, :st_approved )";

    protected static $queryForLocalMismatches = "
                SELECT
                translation,
                jobs.source as source,
                jobs.target as target,
                COUNT( distinct id_segment ) as TOT,
                GROUP_CONCAT( distinct id_segment ) AS involved_id,
                IF( password = :job_password AND id_segment between job_first_segment AND job_last_segment, 1, 0 ) AS editable
                    FROM segment_translations
                    JOIN jobs ON id_job = id AND id_segment between :job_first_segment AND :job_last_segment
                    WHERE segment_hash = (
                        SELECT segment_hash FROM segments WHERE id = :id_segment
                    )
                    AND segment_translations.status IN( :st_translated , :st_approved )
                    AND id_job = :id_job
                    AND id_segment != :id_segment
                    AND translation != (
                        SELECT translation FROM segment_translations where id_segment = :id_segment and id_job=:id_job
                    )
                    GROUP BY translation, id_job
            ";

    public function countByFile( Files_FileStruct $file ) {
        $conn = $this->database->getConnection();
        $sql  = "SELECT COUNT(1) FROM segments WHERE id_file = :id_file ";

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_file' => $file->id ] );

        return (int)$stmt->fetch()[ 0 ];
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
    public function getByFileId( $id_file, $fields_list = [] ) {
        $conn = $this->database->getConnection();

        if ( empty( $fields_list ) ) {
            $fields_list[] = '*';
        }

        $sql  = " SELECT " . implode( ', ', $fields_list ) . " FROM segments WHERE id_file = :id_file ";
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );
        $stmt->execute( [ 'id_file' => $id_file ] );

        return $stmt->fetchAll();
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return mixed
     */
    function countByChunk( Chunks_ChunkStruct $chunk ) {
        $conn  = $this->database->getConnection();
        $query = "SELECT COUNT(1) FROM segments s
            JOIN segment_translations st ON s.id = st.id_segment
            JOIN jobs ON st.id_job = jobs.id
            WHERE jobs.id = :id_job
            AND jobs.password = :password
            AND s.show_in_cattool ;
            ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [ 'id_job' => $chunk->id, 'password' => $chunk->password ] );
        $result = $stmt->fetch();

        return (int)$result[ 0 ];
    }

    /**
     * @param $id_job
     * @param $password
     * @param $id_segment
     * @param $ttl (default 86400 = 24 hours)
     *
     * @return \Segments_SegmentStruct|\DataAccess_IDaoStruct
     */
    function getByChunkIdAndSegmentId( $id_job, $password, $id_segment, $ttl = 86400 ) {

        $query = " SELECT segments.* FROM segments " .
                " INNER JOIN files_job fj USING (id_file) " .
                " INNER JOIN jobs ON jobs.id = fj.id_job " .
                " INNER JOIN files f ON f.id = fj.id_file " .
                " WHERE jobs.id = :id_job AND jobs.password = :password" .
                " AND segments.id_file = f.id " .
                " AND segments.id = :id_segment ";

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( $query );

        $fetched = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Segments_SegmentStruct(), [
                'id_job'     => $id_job,
                'password'   => $password,
                'id_segment' => $id_segment
        ] );

        return isset( $fetched[ 0 ] ) ? $fetched[ 0 ] : null;
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return Segments_SegmentStruct[]
     */
    function getByChunkId( $id_job, $password ) {
        $conn = $this->database->getConnection();

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

        $stmt->execute( [
                'id_job'   => $id_job,
                'password' => $password
        ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_segment
     *
     * @return Segments_SegmentStruct
     */
    public function getById( $id_segment ) {
        $conn = $this->database->getConnection();

        $query = "select * from segments where id = :id";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [ 'id' => (int)$id_segment ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    /**
     * @param $id_list
     *
     * @return object
     */
    public function getContextAndSegmentByIDs( $id_list ) {
        $query = "SELECT id, segment FROM segments WHERE id IN( :id_before, :id_segment, :id_after ) ORDER BY id ASC";
        $stmt  = $this->_getStatementForCache( $query );
        /** @var $res Segments_SegmentStruct[] */
        $res = $this->_fetchObject( $stmt,
                new Segments_SegmentStruct(),
                $id_list
        );

        $reverse_id_list = @array_flip( $id_list );
        foreach ( $res as $element ) {
            $id_list[ $reverse_id_list[ $element->id ] ] = $element;
        }

        return (object)$id_list;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     * @param int                $step
     * @param                    $ref_segment
     * @param string             $where
     *
     * @param array              $options
     *
     * @return array
     * @throws Exception
     * @internal param $jid
     * @internal param $password
     */
    public function getSegmentsIdForQR( Chunks_ChunkStruct $chunk, $step, $ref_segment, $where = "after", $options = [] ) {

        $db = Database::obtain()->getConnection();

        $options_conditions_query  = "";
        $options_join_query        = "";
        $options_conditions_values = [];
        $statuses                  = array_merge(
                Constants_TranslationStatus::$INITIAL_STATUSES,
                Constants_TranslationStatus::$TRANSLATION_STATUSES,
                Constants_TranslationStatus::$REVISION_STATUSES
        );

        //
        // Note 2020-01-14
        // --------------------------------
        // We added a UNION to this query to include also the unmodified ICE segments translation in R1
        //
        if ( isset( $options[ 'filter' ][ 'status' ] ) && in_array( $options[ 'filter' ][ 'status' ], $statuses ) ) {
            $options_conditions_query              .= " AND st.status = :status ";
            $options_conditions_values[ 'status' ] = $options[ 'filter' ][ 'status' ];

            $union_ice = "UNION
                (SELECT distinct(s.id) AS __sid
                    FROM segments s
                    JOIN segment_translations st ON s.id = st.id_segment
                    JOIN jobs j ON j.id = st.id_job
                    AND j.id = :id_job
                    AND j.password = :password
                    AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
                    AND st.status = :status
                    AND st.version_number = 0 AND st.match_type = 'ICE' AND st.translation_date IS NULL 
                    ORDER BY __sid DESC
                LIMIT %u)";
        } else {
            $union_ice = "";
        }

        if (
                ( isset( $options[ 'filter' ][ 'issue_category' ] ) && $options[ 'filter' ][ 'issue_category' ] != '' ) ||
                ( isset( $options[ 'filter' ][ 'severity' ] ) && $options[ 'filter' ][ 'severity' ] != '' )
        ) {

            $options_join_query .= " LEFT JOIN qa_entries e ON e.id_segment = st.id_segment AND e.id_job = st.id_job AND e.deleted_at IS NULL ";
            $options_join_query .= " LEFT JOIN segment_revisions sr ON sr.id_segment = st.id_segment AND sr.id_job = st.id_job ";

            if (
                    isset( $options[ 'filter' ][ 'issue_category' ] ) &&
                    $options[ 'filter' ][ 'issue_category' ] != '' &&
                    $options[ 'filter' ][ 'issue_category' ] != self::ISSUE_CATEGORY_ALL
            ) {

                // Case for AbstractRevisionFeature
                if ( is_array( $options[ 'filter' ][ 'issue_category' ] ) ) {
                    $placeholders = implode( ', ', array_map( function ( $id ) {
                        return ':issue_category_' . $id;
                    }, $options[ 'filter' ][ 'issue_category' ] ) );

                    $options_conditions_query .= " AND e.id_category IN ( $placeholders ) ";

                    foreach ( $options[ 'filter' ][ 'issue_category' ] as $id_category ) {
                        $options_conditions_values[ 'issue_category_' . $id_category ] = $id_category;
                    }
                } else {
                    $options_conditions_query                   .= " AND e.id_category = :id_category ";
                    $options_conditions_values[ 'id_category' ] = $options[ 'filter' ][ 'issue_category' ];
                }

            } elseif (
                    isset( $options[ 'filter' ][ 'issue_category' ] ) &&
                    $options[ 'filter' ][ 'issue_category' ] == self::ISSUE_CATEGORY_ALL
            ) {
                $options_conditions_query .= " AND e.id_category IS NOT NULL ";
            }


            if ( isset( $options[ 'filter' ][ 'severity' ] ) && $options[ 'filter' ][ 'severity' ] != '' ) {
                $options_conditions_query                .= " AND e.severity = :severity ";
                $options_conditions_values[ 'severity' ] = $options[ 'filter' ][ 'severity' ];
            }
        }

        if ( isset( $options[ 'filter' ][ 'id_segment' ] ) && !empty( $options[ 'filter' ][ 'id_segment' ] ) ) {
            $options_conditions_query                  .= " AND s.id = :id_segment ";
            $options_conditions_values[ 'id_segment' ] = $options[ 'filter' ][ 'id_segment' ];

        }

        $queryAfter = "
                SELECT * FROM (
                    (SELECT distinct(s.id) AS __sid
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
                    LIMIT %u)
                    $union_ice
                ) AS TT1";

        $queryBefore = "
                SELECT * FROM (
                    (SELECT distinct(s.id) AS __sid
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
                    LIMIT %u)
                    $union_ice
                ) as TT2";

        /*
         * This query is an union of the last two queries with only one difference:
         * the queryAfter parts differs for the equal sign.
         *
         */
        $queryCenter = "
                  SELECT * FROM ( 
                        (SELECT DISTINCT(s.id) AS __sid
                        FROM segments s
                        JOIN segment_translations st ON s.id = st.id_segment
                        JOIN jobs j ON j.id = st.id_job
                        %s
                        WHERE st.id_job = :id_job
                            AND j.password = :password
                            AND s.show_in_cattool = 1
                            AND s.id >= :ref_segment
                            %s
                        LIMIT %u )
                        $union_ice
                  ) AS TT1
                  UNION
                  SELECT * FROM (
                        SELECT DISTINCT(s.id) AS __sid
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
                $subQuery = sprintf( $queryAfter, $options_join_query, $options_conditions_query, $step, $step );
                break;
            case 'before':
                $subQuery = sprintf( $queryBefore, $options_join_query, $options_conditions_query, $step, $step );
                break;
            case 'center':
                $subQuery = sprintf( $queryCenter, $options_join_query, $options_conditions_query, $step, $step, $options_join_query, $options_conditions_query, $step );
                break;
            default:
                throw new Exception( "No direction selected" );
        }

        $stmt              = $db->prepare( $subQuery );
        $conditions_values = array_merge( [
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'ref_segment' => $ref_segment
        ], $options_conditions_values );

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

    public function getSegmentsForQr( $segments_id, $job_id, $job_password ) {
        $db = Database::obtain()->getConnection();

        $prepare_str_segments_id = str_repeat( 'UNION SELECT ? ', count( $segments_id ) - 1 );

        reset( $segments_id );
        $min = current( $segments_id );
        $max = end( $segments_id );

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
                st.version_number,
                ste.source_page
                
                FROM segments s
                RIGHT JOIN segment_translations st ON st.id_segment = s.id
                RIGHT JOIN jobs j ON j.id = st.id_job
                RIGHT JOIN files_job fj ON fj.id_job = j.id
                RIGHT JOIN files f ON f.id = fj.id_file AND s.id_file = f.id

                LEFT JOIN (
                
                	SELECT id_segment as ste_id_segment, source_page 
                    FROM  segment_translation_events 
                    JOIN ( 
                        SELECT max(id) as _m_id FROM segment_translation_events
                            WHERE id_job = ?
                            AND id_segment BETWEEN ? AND ?
                            GROUP BY id_segment 
                        ) AS X ON _m_id = segment_translation_events.id
                    ORDER BY id_segment

                ) ste ON ste.ste_id_segment = s.id

                JOIN (
                    SELECT ? as id_segment
                    " . $prepare_str_segments_id . "
                 ) AS SLIST USING( id_segment )

                 WHERE j.id = ? AND j.password = ?

            ORDER BY sid ASC";

        $stmt = $db->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_CLASS, "\QualityReport_QualityReportSegmentStruct" );
        $stmt->execute( array_merge( [ $job_id, $min, $max ], $segments_id, [ $job_id, $job_password ] ) );

        $results = $stmt->fetchAll();

        return $results;
    }

    /**
     * @param Segments_SegmentStruct[] $obj_arr
     *
     * @throws Exception
     */
    public function createList( array $obj_arr ) {

        $obj_arr = array_chunk( $obj_arr, 100 );

        $baseQuery = "INSERT INTO segments ( 
                            id, 
                            internal_id, 
                            id_file,
                            id_file_part,
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


        Log::doJsonLog( "Segments: Total Queries to execute: " . count( $obj_arr ) );

        $tuple_marks = "( " . rtrim( str_repeat( "?, ", 13 ), ", " ) . " )";  //set to 13 when implements id_project

        foreach ( $obj_arr as $i => $chunk ) {

            $query = $baseQuery . rtrim( str_repeat( $tuple_marks . ", ", count( $chunk ) ), ", " );

            $values = [];
            foreach ( $chunk as $segStruct ) {

                $values[] = $segStruct->id;
                $values[] = $segStruct->internal_id;
                $values[] = $segStruct->id_file;
                $values[] = $segStruct->id_file_part;
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

                $stm = $this->database->getConnection()->prepare( $query );
                $stm->execute( $values );
                Log::doJsonLog( "Segments: Executed Query " . ( $i + 1 ) );

            } catch ( PDOException $e ) {
                Log::doJsonLog( "Segment import - DB Error: " . $e->getMessage() );
                throw new Exception( "Segment import - DB Error: " . $e->getMessage() . " - " . var_export( $chunk, true ), -2 );
            }

        }


    }

    /**
     * @param Jobs_JobStruct $jStruct
     * @param                $step
     * @param                $ref_segment
     * @param                $where
     * @param array          $options
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getPaginationSegments( Jobs_JobStruct $jStruct, $step, $ref_segment, $where, $options = [] ) {

        switch ( $where ) {
            case 'after':
                $step     = $step * 2;
                $subQuery = "
                SELECT * FROM (
                    SELECT segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = :id_job
                        AND password = :password
                        AND show_in_cattool = 1
                        AND segments.id > :ref_segment
                    LIMIT $step
                ) AS TT1
                ";
                break;
            case 'before':
                $step     = $step * 2;
                $subQuery = "
                SELECT * FROM (
                    SELECT  segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id =  id_job
                    WHERE id_job = :id_job
                        AND password = :password
                        AND show_in_cattool = 1
                        AND segments.id < :ref_segment
                    ORDER BY __sid DESC
                    LIMIT $step
                ) as TT2
                ";
                break;
            case 'center':
                $subQuery = "
                  SELECT * FROM ( 
                        SELECT segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id = id_job
                        WHERE id_job = :id_job
                            AND password = :password
                            AND show_in_cattool = 1
                            AND segments.id >= :ref_segment
                        LIMIT $step
                  ) AS TT1
                  UNION
                  SELECT * FROM (
                        SELECT  segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = :id_job
                            AND password = :password
                            AND show_in_cattool = 1
                            AND segments.id < :ref_segment
                        ORDER BY __sid DESC
                        LIMIT $step
                  ) AS TT2
    ";
                break;
        }

        $optional_fields = "";
        if ( isset( $options[ 'optional_fields' ] ) && !empty( $options[ 'optional_fields' ] ) ) {
            $optional_fields = ', ' . implode( ', ', $options[ 'optional_fields' ] );
        }

        $query = "SELECT j.id AS jid,
                s.id_file,
                s.id_file_part,
                files.filename,
                s.id AS sid,
                s.segment,
                s.segment_hash,
                IF ( st.status='NEW', NULL, st.translation ) AS translation,
                IF( st.locked AND match_type = 'ICE', 1, 0 ) AS ice_locked,
                st.status,
                COALESCE( time_to_edit, 0 ) AS time_to_edit,
                st.warning,
                sts.source_chunk_lengths,
                sts.target_chunk_lengths,
                sod.map AS data_ref_map,
                IF( ( s.id BETWEEN j.job_first_segment AND j.job_last_segment ) , 'false', 'true' ) AS readonly
                , COALESCE( autopropagated_from, 0 ) as autopropagated_from
                ,( SELECT COUNT( segment_hash )
                          FROM segment_translations
                          WHERE segment_hash = s.segment_hash
                          AND id_job =  j.id
                ) repetitions_in_chunk

                $optional_fields

                FROM segments s
                JOIN files ON files.id = s.id_file
                JOIN segment_translations st ON st.id_segment = s.id
                JOIN jobs j ON j.id = st.id_job
                LEFT JOIN segment_translations_splits sts ON sts.id_segment = s.id AND sts.id_job = :id_job
                LEFT JOIN segment_original_data sod ON sod.id_segment = s.id
                JOIN (

                  $subQuery

                ) AS TEMP ON TEMP.__sid = s.id

            WHERE j.id = :id_job
            AND j.password = :password
            ORDER BY sid ASC
";

        $bind_keys = [
                'id_job'      => $jStruct->id,
                'password'    => $jStruct->password,
                'ref_segment' => $ref_segment
        ];

        $stm = $this->getDatabaseHandler()->getConnection()->prepare( $query );

        return $this->_fetchObject( $stm, new SegmentUIStruct(), $bind_keys );

    }

    /**
     * @param Jobs_JobStruct $jStruct
     * @param                $id_file
     *
     * @return array
     */
    public function getSegmentsDownload( Jobs_JobStruct $jStruct, $id_file ) {

        $query = "SELECT
            s.id AS sid, 
            s.segment, 
            s.internal_id,
            s.xliff_mrk_id AS mrk_id, 
            s.xliff_ext_prec_tags AS prev_tags, 
            s.xliff_ext_succ_tags AS succ_tags,
            s.xliff_mrk_ext_prec_tags AS mrk_prev_tags, 
            s.xliff_mrk_ext_succ_tags AS mrk_succ_tags,
            st.translation, 
            st.status,
            st.serialized_errors_list AS error,
            st.eq_word_count,
            s.raw_word_count,
            ste.source_page,
            IF( LOCATE( '3', ste.source_page ) > 0, 1, null ) AS r2,
            od.map as data_ref_map
        FROM files 
        JOIN segments s ON s.id_file = files.id
        LEFT JOIN segment_translations st ON s.id = st.id_segment AND st.id_job = :id_job
        LEFT JOIN segment_original_data od ON s.id = od.id_segment
        LEFT JOIN segment_translation_events ste ON s.id = ste.id_segment 
				AND ste.id_job = st.id_job
				AND ste.source_page = 3
				AND ste.version_number = st.version_number
				AND ste.final_revision = 1
        WHERE files.id = :id_file group by sid
";

        $bind_keys = [
                'id_job'  => $jStruct->id,
                'id_file' => $id_file
        ];

        $stm = $this->getDatabaseHandler()->getConnection()->prepare( $query );

        $stm->setFetchMode( PDO::FETCH_ASSOC );
        $stm->execute( $bind_keys );

        return $stm->fetchAll();

    }

    public function destroyCacheForGlobalTranslationMismatches( Jobs_JobStruct $job ) {
        $stmt = $this->_getStatementForCache( self::$queryForGlobalMismatches );

        return $this->_destroyObjectCache( $stmt, [
                'id_job'        => $job->id,
                'st_approved'   => Constants_TranslationStatus::STATUS_APPROVED,
                'st_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
        ] );
    }

    /**
     * @param int    $jid
     * @param string $jpassword
     * @param null   $sid
     *
     * @return array
     */
    public function getTranslationsMismatches( $jid, $jpassword, $sid = null ) {

        $jStructs = Jobs_JobDao::getById( $jid, $this->cacheTTL );
        $filtered = array_filter( $jStructs, function ( $item ) use ( $jpassword ) {
            return $item->password == $jpassword;
        } );

        $currentJob = array_pop( $filtered );

        if ( empty( $currentJob ) || empty( $currentJob->id ) ) {
            return [];
        }

        /**
         * Get all the available translations for this segment id,
         * the amount of equal translations,
         * a list of id,
         * and an editable boolean field identifying if jobs is mine or not
         *
         * ---------------------------------------
         * NOTE 2020-07-07
         * ---------------------------------------
         * A more strict condition was added in order to check the translation mismatch
         *
         */
        if ( $sid != null ) {

            $stmt = $this->_getStatementForCache( self::$queryForLocalMismatches );

            return $this->_fetchObject( $stmt, new ShapelessConcreteStruct, [
                            'job_first_segment' => $jStructs[ 0 ]->job_first_segment,
                            'job_last_segment'  => end( $jStructs )->job_last_segment,
                            'job_password'      => $currentJob->password,
                            'st_approved'       => Constants_TranslationStatus::STATUS_APPROVED,
                            'st_translated'     => Constants_TranslationStatus::STATUS_TRANSLATED,
                            'id_job'            => $jStructs[ 0 ]->id,
                            'id_segment'        => $sid
                    ]
            );

        } else {

            /*
             * This block of code make the same of this query, but it is ~10 times faster for jobs that are split in a big number of chunks.
             * From 10s to 1,5s
             *
             *            $query = "
             *                SELECT
             *                COUNT( segment_hash ) AS total_sources,
             *                COUNT( DISTINCT translation ) AS translations_available,
             *                MIN( IF( password = :chunk_password AND id_segment between :chunk_first_segment AND :chunk_last_segment,  id_segment , NULL ) ) AS first_of_my_job
             *                    FROM segment_translations
             *                    JOIN jobs ON id_job = id AND id_segment between :job_first_segment AND :job_last_segment
             *                    WHERE id_job = :id_job
             *                    AND segment_translations.status IN( :st_translated , :st_approved )
             *                    GROUP BY segment_hash, CONCAT( id_job, '-', password )
             *                    HAVING translations_available > 1
             *            ";
             */
            $stmt = $this->_getStatementForCache( self::$queryForGlobalMismatches );
            $list = $this->_fetchObject( $stmt, new ShapelessConcreteStruct, [
                            'id_job'        => $currentJob->id,
                            'st_approved'   => Constants_TranslationStatus::STATUS_APPROVED,
                            'st_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
                    ]
            );

            // create a specific array with segment id as key
            $segment_list = [];
            array_walk( $list, function ( $element ) use ( &$segment_list ) {
                $segment_list[ $element[ 'id_segment' ] ] = $element;
            } );
            unset( $list );


            $twin_segments             = [];
            $reverse_translation_index = [];
            foreach ( $jStructs as $job ) {

                for ( $i = $job->job_first_segment; $i <= $job->job_last_segment; $i++ ) { // iterate only the job contained segments to avoid O( N^2 )

                    // segment_translations do not hold all the segments if they are not translatable, skip
                    if ( !isset( $segment_list[ $i ] ) ) {
                        continue;
                    }

                    $segment_hash = $segment_list[ $i ][ 'segment_hash' ];
                    $translation  = $segment_list[ $i ][ 'translation' ];
                    $unique_key   = md5( $translation . $segment_hash );

                    if ( !isset( $twin_segments[ $segment_hash ] ) ) {
                        $twin_segments[ $segment_hash ] = [
                                'total_sources'          => 0,
                                'translations'           => [],
                                'first_of_my_job'        => null,
                                'translations_available' => 0
                        ];
                    }

                    $twin_segments[ $segment_hash ][ 'total_sources' ] += 1;

                    // array_unique : the translation related to a specific segment_hash
                    if ( !isset( $reverse_translation_index[ $unique_key ] ) ) {
                        $twin_segments[ $segment_hash ][ 'translations' ][] = $translation;
                    }

                    $reverse_translation_index[ $unique_key ] = $segment_hash;

                    if ( $job->password == $currentJob->password && !isset( $twin_segments[ $segment_hash ][ 'first_of_my_job' ] ) ) {
                        $twin_segments[ $segment_hash ][ 'first_of_my_job' ] = $i;
                    }

                }

            }

            unset( $reverse_translation_index );
            unset( $segment_list );

            foreach ( $twin_segments as $segment_hash => $element ) {
                if ( $element[ 'total_sources' ] > 1 && count( $element[ 'translations' ] ) > 1 && isset( $element[ 'first_of_my_job' ] ) ) {
                    $twin_segments[ $segment_hash ][ 'translations_available' ] = count( $element[ 'translations' ] );
                    unset( $twin_segments[ $segment_hash ][ 'translations' ] ); // free memory
                } else {
                    unset( $twin_segments[ $segment_hash ] ); // remove unwanted segments
                }
            }

            return array_values( $twin_segments );

            /**
             * This query gets, for each hash with more than one translation available, the min id of the segments
             *
             * If we want also to check for mismatches against approved translations also,
             * we have to add the APPROVED status condition.
             *
             * But be careful, queries are much more heaviest.
             * ( Ca. 4X -> 0.01/0.02s for a job with 55k segments on a dev environment )
             *
             */
//            $query = "
//                SELECT
//                COUNT( segment_hash ) AS total_sources,
//                COUNT( DISTINCT translation ) AS translations_available,
//                MIN( IF( password = :chunk_password AND id_segment between :chunk_first_segment AND :chunk_last_segment,  id_segment , NULL ) ) AS first_of_my_job
//                    FROM segment_translations
//                    JOIN jobs ON id_job = id AND id_segment between :job_first_segment AND :job_last_segment
//                    WHERE id_job = :id_job
//                    AND segment_translations.status IN( :st_translated , :st_approved )
//                    GROUP BY segment_hash, CONCAT( id_job, '-', password )
//                    HAVING translations_available > 1
//            ";
//
//            $bind_keys = [
//                    'id_job'              => $currentJob->id,
//                    'job_first_segment'   => $jStructs[ 0 ]->job_first_segment,
//                    'job_last_segment'    => end( $jStructs )->job_last_segment,
//                    'chunk_first_segment' => $currentJob->job_first_segment,
//                    'chunk_last_segment'  => $currentJob->job_last_segment,
//                    'chunk_password'      => $currentJob->password,
//                    'st_approved'         => Constants_TranslationStatus::STATUS_APPROVED,
//                    'st_translated'       => Constants_TranslationStatus::STATUS_TRANSLATED,
//            ];

        }

    }

    /**
     * Used to get a resultset of segments id and statuses
     *
     * @param      $sid
     * @param      $jid
     * @param      $password
     * @param bool $getTranslatedInstead
     *
     * @return array
     */
    public static function getNextSegment( $sid, $jid, $password = '', $getTranslatedInstead = false ) {

        if ( !$getTranslatedInstead ) {
            $translationStatus = " ( st.status IN (
                '" . Constants_TranslationStatus::STATUS_NEW . "',
                '" . Constants_TranslationStatus::STATUS_DRAFT . "',
                '" . Constants_TranslationStatus::STATUS_REJECTED . "'
            ) OR st.status IS NULL )"; //status NULL is not possible
        } else {
            $translationStatus = " st.status IN(
            '" . Constants_TranslationStatus::STATUS_TRANSLATED . "',
            '" . Constants_TranslationStatus::STATUS_APPROVED . "'
        )";
        }

        $bind_values = [
                'jid'      => $jid,
                'sid'      => $sid,
                'password' => $password
        ];

        $query = "SELECT s.id, st.status
		FROM segments AS s
		JOIN segment_translations st ON st.id_segment = s.id
		JOIN jobs ON jobs.id = st.id_job
		WHERE jobs.id = :jid 
		AND jobs.password = :password
		AND $translationStatus
		AND s.show_in_cattool = 1
		AND s.id <> :sid
		AND s.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment
		";

        $stmt = Database::obtain()->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( $bind_values );

        return $stmt->fetchAll();

    }

    /**
     * @param     $idJob
     * @param     $password
     * @param     $limit
     * @param     $offset
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public static function getSegmentsForAnalysisFromIdJobAndPassword( $idJob, $password, $limit, $offset, $ttl = 0 ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $query   = "
            SELECT 
                p.name as project_name,
                s.id,
                j.id as id_job,
                j.password as job_password,
                j.source,
                j.target,
                s.segment,
                st.translation,
                st.status,  
                s.raw_word_count,
                st.eq_word_count,
                st.match_type,
                ste.source_page,
                ste.create_date as last_edit,
                f.filename,
                fp.tag_key,
                fp.tag_value,
                IF( LOCATE( '1', _page ) > 0, 1, null ) AS has_t,
                IF( LOCATE( '2', _page ) > 0, 2, null ) AS has_r1,
                IF( LOCATE( '3', _page ) > 0, 3, null ) AS has_r2
            FROM
                jobs j
            JOIN 
                projects p ON p.id = j.id_project
            JOIN
                segment_translations st ON j.id = st.id_job AND st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment
            JOIN 
                segments s on s.id = st.id_segment 
            LEFT JOIN
                files f ON s.id_file = f.id
            LEFT JOIN
                files_parts fp ON fp.id = s.id_file_part     
            LEFT JOIN (
                SELECT id_job, id_segment, group_concat( distinct source_page )  as _page
                FROM segment_translation_events stex
                JOIN
                    jobs j ON stex.id_job = j.id
                WHERE stex.id_job = j.id
                    AND j.id = :id_job 
                    AND j.password = :password
                GROUP BY stex.id_segment
            ) AS XX ON XX.id_segment = st.id_segment
            LEFT JOIN
                (
                    SELECT 
                       id_segment AS ste_id_segment, source_page, create_date
                    FROM
                        segment_translation_events
                    JOIN (
                        SELECT 
                            MAX(ste.id) AS _m_id
                                FROM
                            segment_translation_events ste
                        JOIN 
                            jobs j ON ste.id_job = j.id
                        JOIN 
                            projects p ON p.id = j.id_project
                        WHERE
                            j.id = :id_job 
                        AND
                            j.password = :password
                        AND id_segment BETWEEN j.job_first_segment AND ( j.job_first_segment + " . $offset . " )
                            GROUP BY id_segment
                            LIMIT " . $limit . "
                        ) AS X ON _m_id = segment_translation_events.id
                ) ste ON ste.ste_id_segment = st.id_segment
            WHERE
                j.id = :id_job
            AND 
                j.password = :password
            LIMIT " . $limit . " offset " . $offset;

        $stmt = $conn->prepare( $query );

        return @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'   => $idJob,
                'password' => $password,
        ] );
    }

    /**
     * @param     $idProject
     * @param     $password
     * @param     $limit
     * @param     $offset
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public static function getSegmentsForAnalysisFromIdProjectAndPassword( $idProject, $password, $limit, $offset, $ttl = 0 ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $query   = "
            SELECT 
                p.name as project_name,
                s.id,
                j.id as id_job,
                j.password as job_password,
                j.source,
                j.target,
                s.segment,
                st.translation,
                st.status,  
                s.raw_word_count,
                st.eq_word_count,
                st.match_type,
                ste.source_page,
                ste.create_date as last_edit,
                f.filename,
                fp.tag_key,
                fp.tag_value,
                IF( LOCATE( '1', _page ) > 0, 1, null ) AS has_t,
                IF( LOCATE( '2', _page ) > 0, 2, null ) AS has_r1,
                IF( LOCATE( '3', _page ) > 0, 3, null ) AS has_r2
            FROM
                jobs j
            JOIN 
                projects p ON p.id = j.id_project
            JOIN
                segment_translations st ON j.id = st.id_job AND st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment
            JOIN 
                segments s on s.id = st.id_segment 
            LEFT JOIN
                files f ON s.id_file = f.id
            LEFT JOIN
                files_parts fp ON fp.id = s.id_file_part     
            LEFT JOIN (
                SELECT id_job, id_segment, group_concat( distinct source_page )  as _page
                FROM segment_translation_events stex
                JOIN
                    jobs j ON stex.id_job = j.id
                WHERE stex.id_job = j.id
                    AND id_project = :id_project
                GROUP BY stex.id_segment
            ) AS XX ON XX.id_segment = st.id_segment
            LEFT JOIN
                (
                    SELECT 
                       id_segment AS ste_id_segment, source_page, create_date
                    FROM
                        segment_translation_events
                    JOIN (
                        SELECT 
                            MAX(ste.id) AS _m_id
                                FROM
                            segment_translation_events ste
                        JOIN 
                            jobs j ON ste.id_job = j.id
                        JOIN 
                            projects p ON p.id = j.id_project
                        WHERE
                            p.id = :id_project
                        AND id_segment BETWEEN j.job_first_segment AND j.job_last_segment
                            GROUP BY id_segment
                        ) AS X ON _m_id = segment_translation_events.id
                ) ste ON ste.ste_id_segment = st.id_segment
            WHERE
                p.id = :id_project
            AND 
                p.password = :password
            LIMIT " . $limit . " offset " . $offset;

        $stmt = $conn->prepare( $query );

        return @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_project' => $idProject,
                'password'   => $password,
        ] );
    }
}
