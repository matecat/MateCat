<?php

use Features\ReviewExtended\ReviewUtils;
use Segments\SegmentUIStruct;

class Segments_SegmentDao extends DataAccess_AbstractDao {
    const TABLE = 'segments';
    protected static $auto_increment_field = [ 'id' ];


    const ISSUE_CATEGORY_ALL = 'all';

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
     *
     * @return \Segments_SegmentStruct
     */
    function getByChunkIdAndSegmentId( $id_job, $password, $id_segment ) {
        $conn = $this->database->getConnection();

        $query = " SELECT segments.* FROM segments " .
                " INNER JOIN files_job fj USING (id_file) " .
                " INNER JOIN jobs ON jobs.id = fj.id_job " .
                " INNER JOIN files f ON f.id = fj.id_file " .
                " WHERE jobs.id = :id_job AND jobs.password = :password" .
                " AND segments.id_file = f.id " .
                " AND segments.id = :id_segment ";

        $stmt = $conn->prepare( $query );

        $stmt->execute( [
                'id_job'     => $id_job,
                'password'   => $password,
                'id_segment' => $id_segment
        ] );

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
        $res             = $this->_fetchObject( $stmt,
                new Segments_SegmentStruct(),
                $id_list
        );
        $reverse_id_list = array_flip( $id_list );
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
    public function getSegmentsIdForQR( Chunks_ChunkStruct $chunk, $step = 20, $ref_segment, $where = "after", $options = [] ) {

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

                if ( in_array( $options[ 'filter' ][ 'issue_category' ], Constants_Revise::$categoriesDbNames ) ) {
                    // Case for legacy review  ^^
                    $options_conditions_query .= " AND (sr." . $options[ 'filter' ][ 'issue_category' ] .
                            " != '' AND sr." . $options[ 'filter' ][ 'issue_category' ] . " != 'none')";
                } else {
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
                }
            } elseif (
                    isset( $options[ 'filter' ][ 'issue_category' ] ) &&
                    $options[ 'filter' ][ 'issue_category' ] == self::ISSUE_CATEGORY_ALL
            ) {
                if ( $chunk->getProject()->getFeaturesSet()->hasRevisionFeature() ) {
                    $options_conditions_query .= " AND e.id_category IS NOT NULL ";
                } else {
                    $options_conditions_query .= " AND ( " .
                            implode( ' OR ', array_map( function ( $name ) {
                                return " ( sr.$name != 'none' AND sr.$name != '') ";
                            }, Constants_Revise::$categoriesDbNames ) ) . " ) ";
                }
            }


            if ( isset( $options[ 'filter' ][ 'severity' ] ) && $options[ 'filter' ][ 'severity' ] != '' ) {
                $options_conditions_query                .= " AND (
                    e.severity = :severity OR (
                        sr.err_typing = :severity OR
                        sr.err_translation = :severity OR
                        sr.err_terminology = :severity OR
                        sr.err_language = :severity OR
                        sr.err_style = :severity)
                        ) ";
                $options_conditions_values[ 'severity' ] = $options[ 'filter' ][ 'severity' ];
            }
        }

        if ( isset( $options[ 'filter' ][ 'id_segment' ] ) && !empty( $options[ 'filter' ][ 'id_segment' ] ) ) {
            $options_conditions_query                  .= " AND s.id = :id_segment ";
            $options_conditions_values[ 'id_segment' ] = $options[ 'filter' ][ 'id_segment' ];

        }

        if ( isset( $options[ 'filter' ][ 'revision_number' ] ) && !empty( $options[ 'filter' ][ 'revision_number' ] ) ) {
            $join_revision_number = " JOIN segment_translation_events ste on s.id = ste.id_segment " .
                    " AND ste.id_job = j.id  " .
                    " AND ste.source_page = :source_page  " .
                    " AND ste.version_number = st.version_number " .
                    " AND ste.final_revision = 1 ";

            $options_conditions_values[ 'source_page' ] = ReviewUtils::revisionNumberToSourcePage(
                    $options[ 'filter' ] [ 'revision_number' ]
            );
        } else {
            $join_revision_number = '';
        }

        $queryAfter = "
                SELECT * FROM (
                    (SELECT distinct(s.id) AS __sid
                    FROM segments s
                    JOIN segment_translations st ON s.id = st.id_segment
                    JOIN jobs j ON j.id = st.id_job
                    $join_revision_number
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
                    $join_revision_number
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
                        $join_revision_number
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
                $subQuery = sprintf( $queryAfter, $options_join_query, $options_conditions_query, (int)( $step / 2 ), (int)( $step / 2 ) );
                break;
            case 'before':
                $subQuery = sprintf( $queryBefore, $options_join_query, $options_conditions_query, (int)( $step / 2 ), (int)( $step / 2 ) );
                break;
            case 'center':
                $subQuery = sprintf( $queryCenter, $options_join_query, $options_conditions_query, (int)( $step / 2 ), (int)( $step / 2 ), $options_join_query, $options_conditions_query, (int)( $step / 2 ) );
                break;
            default:
                throw new Exception( "No direction selected" );
                break;
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


        Log::doJsonLog( "Segments: Total Queries to execute: " . count( $obj_arr ) );

        $tuple_marks = "( " . rtrim( str_repeat( "?, ", 12 ), ", " ) . " )";  //set to 13 when implements id_project

        foreach ( $obj_arr as $i => $chunk ) {

            $query = $baseQuery . rtrim( str_repeat( $tuple_marks . ", ", count( $chunk ) ), ", " );

            $values = [];
            foreach ( $chunk as $segStruct ) {

                $values[] = $segStruct->id;
                $values[] = $segStruct->internal_id;
                $values[] = $segStruct->id_file;
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
            st.eq_word_count,
            s.raw_word_count
        FROM files 
        JOIN segments s ON s.id_file = files.id
        LEFT JOIN segment_translations st ON s.id = st.id_segment AND st.id_job = :id_job
        WHERE files.id = :id_file
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

    public function countThisTranslatedHashInJob( $jid, $jpassword, $sid ) {

        $isPropagationToAlreadyTranslatedAvailable = "
        SELECT COUNT(segment_hash) AS available
        FROM segment_translations
        JOIN jobs ON id_job = id AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
        WHERE segment_hash = (
            SELECT segment_hash FROM segments WHERE id = :id_segment
        )
        AND id_job = :id_job
        AND id_segment != :id_segment
        AND password = :password
        AND segment_translations.status IN( 
          '" . Constants_TranslationStatus::STATUS_TRANSLATED . "' , 
          '" . Constants_TranslationStatus::STATUS_APPROVED . "' 
        )
    ";

        $bind_keys = [
                'id_job'     => $jid,
                'password'   => $jpassword,
                'id_segment' => $sid
        ];

        $stm = $this->getDatabaseHandler()->getConnection()->prepare( $isPropagationToAlreadyTranslatedAvailable );
        $stm->setFetchMode( PDO::FETCH_ASSOC );
        $stm->execute( $bind_keys );

        return $stm->fetch();

    }

    /**
     * @param int    $jid
     * @param string $jpassword
     * @param null   $sid
     *
     * @return array
     */
    public function getTranslationsMismatches( $jid, $jpassword, $sid = null ) {

        $st_translated = Constants_TranslationStatus::STATUS_TRANSLATED;
        $st_approved   = Constants_TranslationStatus::STATUS_APPROVED;

        $jStructs = Jobs_JobDao::getById( $jid );
        $filtered = array_filter( $jStructs, function ( $item ) use ( $jpassword ) {
            return $item->password == $jpassword;
        } );

        $currentJob = array_pop( $filtered );

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

            $query = "
                SELECT
                translation,
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

            $bind_keys = [
                    'job_first_segment' => $jStructs[ 0 ]->job_first_segment,
                    'job_last_segment'  => end( $jStructs )->job_last_segment,
                    'job_password'      => $currentJob->password,
                    'st_approved'       => $st_approved,
                    'st_translated'     => $st_translated,
                    'id_job'            => $jStructs[ 0 ]->id,
                    'id_segment'        => $sid
            ];

        } else {
            //TODO cache this query?
            /**
             * This query gets, for each hash with more than one translations available, the min id of the segments
             *
             * If we want also to check for mismatches against approved translations also,
             * we have to add the APPROVED status condition.
             *
             * But be careful, queries are much more heaviest.
             * ( Ca. 4X -> 0.01/0.02s for a job with 55k segments on a dev environment )
             *
             */
            $query = "
                SELECT
                COUNT( segment_hash ) AS total_sources,
                COUNT( DISTINCT translation ) AS translations_available,
                IF( password = :job_password, MIN( id_segment ), NULL ) AS first_of_my_job
                    FROM segment_translations
                    JOIN jobs ON id_job = id AND id_segment between :job_first_segment AND :job_last_segment
                    WHERE id_job = :id_job
                    AND segment_translations.status IN( :st_translated , :st_approved )
                    GROUP BY segment_hash, CONCAT( id_job, '-', password )
                    HAVING translations_available > 1
            ";

            $bind_keys = [
                    'job_first_segment' => $currentJob->job_first_segment,
                    'job_last_segment'  => $currentJob->job_last_segment,
                    'job_password'      => $currentJob->password,
                    'st_approved'       => $st_approved,
                    'st_translated'     => $st_translated,
                    'id_job'            => $currentJob->id,
            ];

        }

        $stm = $this->getDatabaseHandler()->getConnection()->prepare( $query );
        $stm->setFetchMode( PDO::FETCH_ASSOC );
        $stm->execute( $bind_keys );

        return $stm->fetchAll();

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

}
