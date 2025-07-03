<?php

namespace Model\Segments;

use Constants_TranslationStatus;
use Exception;
use Log;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Database;
use Model\Files\FileStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\QualityReport\QualityReportSegmentStruct;
use PDO;
use PDOException;
use ReflectionException;

class SegmentDao extends AbstractDao {
    const TABLE = 'segments';
    protected static array $auto_increment_field = [ 'id' ];


    const ISSUE_CATEGORY_ALL = 'all';

    protected static string $queryForGlobalMismatches = " SELECT id_segment, id_job , segment_hash, translation 
                         FROM segment_translations 
                         WHERE id_job = :id_job
                         AND segment_translations.status IN( :st_translated, :st_approved, :st_approved2 )";

    protected static string $queryForLocalMismatches = "
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
                    AND segment_translations.status IN( :st_translated, :st_approved, :st_approved2 )
                    AND id_job = :id_job
                    AND id_segment != :id_segment
                    AND translation != (
                        SELECT translation FROM segment_translations where id_segment = :id_segment and id_job=:id_job
                    )
                    GROUP BY translation, id_job
            ";

    public function countByFile( FileStruct $file ): int {
        $conn = $this->database->getConnection();
        $sql  = "SELECT COUNT(1) FROM segments WHERE id_file = :id_file ";

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_file' => $file->id ] );

        return (int)$stmt->fetch()[ 0 ];
    }

    /**
     * @param int    $id_job
     * @param string $password
     * @param int    $id_segment
     * @param int    $ttl (default 86400 = 24 hours)
     *
     * @return SegmentStruct|null
     * @throws ReflectionException
     */
    function getByChunkIdAndSegmentId( int $id_job, string $password, int $id_segment, int $ttl = 86400 ): ?SegmentStruct {

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

        /**
         * @var $fetched SegmentStruct[]
         */
        $fetched = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, SegmentStruct::class, [
                'id_job'     => $id_job,
                'password'   => $password,
                'id_segment' => $id_segment
        ] );

        return $fetched[ 0 ] ?? null;
    }

    /**
     * @param int    $id_job
     * @param string $password
     *
     * @return SegmentStruct[]
     */
    function getByChunkId( int $id_job, string $password ): array {
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

        $stmt->setFetchMode( PDO::FETCH_CLASS, SegmentStruct::class );

        return $stmt->fetchAll();
    }

    /**
     * @param int $id_segment
     *
     * @return SegmentStruct
     */
    public function getById( int $id_segment ): ?SegmentStruct {
        $conn = $this->database->getConnection();

        $query = "select * from segments where id = :id";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [ 'id' => $id_segment ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, SegmentStruct::class );

        return $stmt->fetch() ?? null;
    }

    /**
     * @param array $id_list
     *
     * @return object
     * @throws ReflectionException
     */
    public function getContextAndSegmentByIDs( array $id_list ): object {
        $query = "SELECT id, segment FROM segments WHERE id IN( :id_before, :id_segment, :id_after ) ORDER BY id ";
        $stmt  = $this->_getStatementForQuery( $query );
        /** @var $res SegmentStruct[] */
        $res = $this->_fetchObjectMap( $stmt,
                SegmentStruct::class,
                $id_list
        );

        $reverse_id_list = @array_flip( $id_list );
        foreach ( $res as $element ) {
            $id_list[ $reverse_id_list[ $element->id ] ] = $element;
        }

        return (object)$id_list;
    }

    /**
     * @param JobStruct $chunk
     * @param int       $step
     * @param int       $ref_segment
     * @param string    $where
     *
     * @param array     $options
     *
     * @return array
     * @throws Exception
     * @internal param $jid
     * @internal param $password
     */
    public function getSegmentsIdForQR( JobStruct $chunk, int $step, int $ref_segment, string $where = "after", array $options = [] ): array {

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

        if ( !empty( $options[ 'filter' ][ 'id_segment' ] ) ) {
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

        return array_map( function ( $segment_row ) {
            return $segment_row[ '__sid' ];
        }, $segments_id );
    }

    /**
     * @param $segments_id
     * @param $job_id
     * @param $job_password
     *
     * @return QualityReportSegmentStruct[]
     */

    public function getSegmentsForQr( $segments_id, $job_id, $job_password ): array {
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
                st.tm_analysis_status,
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

            ORDER BY sid";

        $stmt = $db->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_CLASS, QualityReportSegmentStruct::class );
        $stmt->execute( array_merge( [ $job_id, $min, $max ], $segments_id, [ $job_id, $job_password ] ) );

        return $stmt->fetchAll();
    }

    /**
     * @param SegmentStruct[] $obj_arr
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
     * @param JobStruct   $jStruct
     * @param int         $step
     * @param int         $ref_segment
     * @param string|null $where
     * @param array       $options
     *
     * @return SegmentUIStruct[]
     * @throws ReflectionException
     */
    public function getPaginationSegments( JobStruct $jStruct, int $step, int $ref_segment, ?string $where = 'center', array $options = [] ): array {

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
            default:
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
        if ( !empty( $options[ 'optional_fields' ] ) ) {
            $optional_fields = ', ' . implode( ', ', $options[ 'optional_fields' ] );
        }

        $query = "SELECT j.id AS jid,
                s.id_file,
                s.id_file_part,
                files.filename,
                s.id AS sid,
                s.segment,
                TO_BASE64(CONCAT(s.id_file_part, '_', s.internal_id)) as internal_id,
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
            ORDER BY sid
";

        $bind_keys = [
                'id_job'      => $jStruct->id,
                'password'    => $jStruct->password,
                'ref_segment' => $ref_segment
        ];

        $stm = $this->getDatabaseHandler()->getConnection()->prepare( $query );

        return $this->_fetchObjectMap( $stm, SegmentUIStruct::class, $bind_keys );

    }

    /**
     * @param JobStruct $jStruct
     * @param int       $id_file
     *
     * @return array
     */
    public function getSegmentsDownload( JobStruct $jStruct, int $id_file ): array {

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

    /**
     * @throws ReflectionException
     */
    public function destroyCacheForGlobalTranslationMismatches( JobStruct $job ) {
        $stmt = $this->_getStatementForQuery( self::$queryForGlobalMismatches );

        return $this->_destroyObjectCache( $stmt, ShapelessConcreteStruct::class, [
                'id_job'        => $job->id,
                'st_approved'   => Constants_TranslationStatus::STATUS_APPROVED,
                'st_approved2'  => Constants_TranslationStatus::STATUS_APPROVED2,
                'st_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
        ] );
    }

    /**
     * @param int      $jid
     * @param string   $jpassword
     * @param int|null $sid
     *
     * @return array
     * @throws ReflectionException
     */
    public function getTranslationsMismatches( int $jid, string $jpassword, int $sid = null ): array {

        $jStructs = JobDao::getById( $jid, $this->cacheTTL );
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

            $stmt = $this->_getStatementForQuery( self::$queryForLocalMismatches );

            return $this->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [
                            'job_first_segment' => $jStructs[ 0 ]->job_first_segment,
                            'job_last_segment'  => end( $jStructs )->job_last_segment,
                            'job_password'      => $currentJob->password,
                            'st_approved'       => Constants_TranslationStatus::STATUS_APPROVED,
                            'st_approved2'      => Constants_TranslationStatus::STATUS_APPROVED2,
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
            $stmt = $this->_getStatementForQuery( self::$queryForGlobalMismatches );
            $list = $this->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [
                            'id_job'        => $currentJob->id,
                            'st_approved'   => Constants_TranslationStatus::STATUS_APPROVED,
                            'st_approved2'  => Constants_TranslationStatus::STATUS_APPROVED2,
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

        }

    }

    /**
     * Used to get a resultset of segments id and statuses
     *
     * @param int    $sid
     * @param int    $jid
     * @param string $password
     * @param bool   $getTranslatedInstead
     *
     * @return array
     */
    public static function getNextSegment( int $sid, int $jid, string $password = '', bool $getTranslatedInstead = false ): array {

        if ( !$getTranslatedInstead ) {
            $translationStatus = " ( st.status IN (
                '" . Constants_TranslationStatus::STATUS_NEW . "',
                '" . Constants_TranslationStatus::STATUS_DRAFT . "',
                '" . Constants_TranslationStatus::STATUS_REJECTED . "'
            ) OR st.status IS NULL )"; //status NULL isn't possible
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
     * @param int    $idJob
     * @param string $password
     * @param int    $limit
     * @param int    $offset
     * @param int    $ttl
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public static function getSegmentsForAnalysisFromIdJobAndPassword( int $idJob, string $password, int $limit, int $offset, int $ttl = 0 ): array {
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
                        AND id_segment BETWEEN (j.job_first_segment + " . $offset . ") AND ( j.job_first_segment + " . ( $limit + $offset ) . " )
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

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [
                'id_job'   => $idJob,
                'password' => $password,
        ] ) ?? [];
    }

    /**
     * @param int    $idProject
     * @param string $password
     * @param int    $limit
     * @param int    $offset
     * @param int    $ttl
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public static function getSegmentsForAnalysisFromIdProjectAndPassword( int $idProject, string $password, int $limit, int $offset, int $ttl = 0 ): array {
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
                        AND id_segment BETWEEN (j.job_first_segment + " . $offset . ") AND ( j.job_first_segment + " . ( $limit + $offset ) . " )
                            GROUP BY id_segment
                            LIMIT " . $limit . "
                        ) AS X ON _m_id = segment_translation_events.id
                ) ste ON ste.ste_id_segment = st.id_segment
            WHERE
                p.id = :id_project
            AND 
                p.password = :password
            LIMIT " . $limit . " offset " . $offset;

        $stmt = $conn->prepare( $query );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [
                'id_project' => $idProject,
                'password'   => $password,
        ] ) ?? [];
    }
}
