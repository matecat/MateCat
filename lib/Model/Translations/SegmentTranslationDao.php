<?php

class Translations_SegmentTranslationDao extends DataAccess_AbstractDao {

    public static $primary_keys = [
            'id_job',
            'id_segment'
    ];

    const TABLE = "segment_translations";

    /**
     * @param     $id_segment
     * @param     $id_job
     *
     * @param int $ttl
     *
     * @return Translations_SegmentTranslationStruct
     */

    public static function findBySegmentAndJob( $id_segment, $id_job, $ttl = 0 ) {
        $conn = Database::obtain()->getConnection();

        $sql = "SELECT * FROM segment_translations WHERE " .
                " id_segment = :id_segment AND " .
                " id_job = :id_job ";

        $stmt = $conn->prepare( $sql );

        $thisDao = new self();

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Translations_SegmentTranslationStruct(), [
                'id_job'     => $id_job,
                'id_segment' => $id_segment
        ] )[ 0 ];
    }

    /**
     * @param $segmentIdList
     * @param $date
     *
     * @return array
     */
    public static function updateLastTranslationDateByIdList( $segmentIdList, $date ) {

        $places = rtrim( str_repeat( " ?,", count( $segmentIdList ) ), "," );

        $conn  = Database::obtain()->getConnection();
        $query = "UPDATE segment_translations SET translation_date = ? WHERE id_segment IN( $places )";
        $stmt  = $conn->prepare( $query );

        $values = array_merge( [ $date ], $segmentIdList );
        $stmt->execute( $values );

    }

    /**
     * @param $chunk
     *
     * @return Translations_SegmentTranslationStruct
     */
    public function lastTranslationByJobOrChunk( $chunk ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * 
                  FROM segment_translations
                    WHERE id_job = :id_job 
                    AND segment_translations.id_segment BETWEEN :job_first_segment AND :job_last_segment 
                  ORDER BY translation_date DESC
                  LIMIT 1 ";
        $stmt = $conn->prepare( $query );
        $array = [
                'id_job'            => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment'  => $chunk->job_last_segment
        ];
        $stmt->execute( $array );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct' );
        return $stmt->fetch();
    }

    public function getSegmentsForPropagation( $params, $status = Constants_TranslationStatus::STATUS_TRANSLATED ) {

        /**
         * We want to avoid that a translation overrides a propagation,
         * so we have to set an additional status when the requested status to propagate is TRANSLATE
         */
        $additional_status = '';
        if ( $status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
            $additional_status = "AND status != '" . Constants_TranslationStatus::STATUS_APPROVED . "'
";
        }

        $selectSegmentsToPropagate = " SELECT * FROM segment_translations " .
                " WHERE id_job = :id_job " .
                " AND segment_hash = :segment_hash " .
                " AND id_segment BETWEEN :job_first_segment AND :job_last_segment " .
                " AND id_segment <> :id_segment $additional_status; ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( $selectSegmentsToPropagate );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct' );
        $stmt->execute( $params );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     *
     * @return Translations_SegmentTranslationStruct[]
     */

    public function getByJobId( $id_job ) {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM segment_translations WHERE id_job = ? " );

        $stmt->execute( [ $id_job ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param Files_FileStruct $file
     *
     * @return Translations_SegmentTranslationStruct[]
     */
    public function getByFile( Files_FileStruct $file ) {
        $sql = "SELECT * FROM segment_translations st " .
                " JOIN segments s on s.id  = st.id_segment AND s.id_file = :id_file " .
                " WHERE s.show_in_cattool = 1 ";

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_file' => $file->id ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct' );

        return $stmt->fetchAll();
    }

    protected function _buildResult( $array_result ) {

    }

    public static function updateSeverity( Translations_SegmentTranslationStruct $struct, $severity ) {
        $sql = "UPDATE segment_translations
            SET warning = :warning
              WHERE id_segment = :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_segment'   => $struct->id_segment,
                'id_job'       => $struct->id_job,
                'segment_hash' => $struct->segment_hash,
                'warning'      => $severity
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param $data
     *
     * @return int
     */
    public static function updateEditDistanceForSetTranslation( $data ) {
        $sql = "UPDATE segment_translations
            SET edit_distance = :edit_distance
              WHERE id_segment = :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $exec = $stmt->execute( [
                'id_segment'    => $data[ 'id_segment' ],
                'id_job'        => $data[ 'id_job' ],
                'segment_hash'  => $data[ 'segment_hash' ],
                'edit_distance' => $data[ 'edit_distance' ]
        ] );

        return $stmt->rowCount();
    }


    public static function updateEditDistanceForPropagation( $data ) {
        $sql = "UPDATE segment_translations
            SET edit_distance = :edit_distance
              WHERE id_segment <> :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash         ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $exec = $stmt->execute( [
                'id_segment'    => $data[ 'id_segment' ],
                'id_job'        => $data[ 'id_job' ],
                'segment_hash'  => $data[ 'segment_hash' ],
                'edit_distance' => $data[ 'edit_distance' ]
        ] );

        return $stmt->rowCount();
    }

    public static function setAnalysisValue( $data ) {

        $where = [
                "id_segment" => $data[ 'id_segment' ],
                "id_job"     => $data[ 'id_job' ]
        ];

        $db = Database::obtain();
        try {
            $affectedRows = $db->update( 'segment_translations', $data, $where );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        return $affectedRows;

    }

    public function setApprovedByChunk( $chunk ) {
        $sql = "UPDATE segment_translations
            SET status = :status
              WHERE id_job = :id_job AND id_segment BETWEEN :first_segment AND :last_segment";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'status'        => Constants_TranslationStatus::STATUS_APPROVED,
                'id_job'        => $chunk->id,
                'first_segment' => $chunk->job_first_segment,
                'last_segment'  => $chunk->job_last_segment
        ] );

        $counter = new \WordCount_CounterModel;
        $counter->initializeJobWordCount( $chunk->id, $chunk->password );

        return $stmt->rowCount();
    }

    public function setTranslatedByChunk( $chunk ) {

        $sql = "UPDATE segment_translations
            SET status = :status
              WHERE id_job = :id_job AND id_segment BETWEEN :first_segment AND :last_segment AND status != :approved_status";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'status'          => Constants_TranslationStatus::STATUS_TRANSLATED,
                'id_job'          => $chunk->id,
                'first_segment'   => $chunk->job_first_segment,
                'last_segment'    => $chunk->job_last_segment,
                'approved_status' => Constants_TranslationStatus::STATUS_APPROVED,
        ] );

        $counter = new \WordCount_CounterModel;
        $counter->initializeJobWordCount( $chunk->id, $chunk->password );

        return $stmt->rowCount();
    }

    public static function getSegmentsWithIssues( $job_id, $segments_ids ) {
        $where_values = $segments_ids;

        $sql  = "SELECT * FROM segment_translations WHERE id_segment IN (" . str_repeat( '?,', count( $segments_ids ) - 1 ) . '?' . ") AND id_job = ?";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );
        $where_values[] = $job_id;
        $stmt->execute( $where_values );

        return $stmt->fetchAll();
    }

    public static function updateSegmentStatusBySegmentId( $id_job, $id_segment, $status ) {
        $sql  = "UPDATE segment_translations SET status = :status WHERE id_job = :id_job AND id_segment = :id_segment ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_job' => $id_job, 'id_segment' => $id_segment, 'status' => $status ] );

        return $stmt->rowCount();
    }

    public static function getUnchangebleStatus( $segments_ids, $status ) {
        $where_values = [];
        $conn         = Database::obtain()->getConnection();

        if ( $status == Constants_TranslationStatus::STATUS_APPROVED ) {
            $where_values[] = Constants_TranslationStatus::STATUS_TRANSLATED;

        } elseif ( $status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
            $where_values[] = Constants_TranslationStatus::STATUS_APPROVED;
            $where_values[] = Constants_TranslationStatus::STATUS_DRAFT;
            $where_values[] = Constants_TranslationStatus::STATUS_NEW;
        } else {
            throw new Exception( 'not allowed to change status to ' . $status );
        }

        $status_placeholders       = str_repeat( '?,', count( $where_values ) - 1 ) . '?';
        $segments_ids_placeholders = str_repeat( '?,', count( $segments_ids ) - 1 ) . '?';

        $sql = "SELECT id_segment FROM segment_translations
                    WHERE
                    (
                      status NOT IN( $status_placeholders )  OR
                      translation IS NULL OR
                      translation = ''
                    ) AND id_segment IN ( $segments_ids_placeholders )
                    ";

        $where_values = array_merge( $where_values, $segments_ids );
        $stmt         = $conn->prepare( $sql );
        $stmt->execute( $where_values );

        return $stmt->fetchAll( PDO::FETCH_FUNC, function ( $id_segment ) {
            return (int)$id_segment;
        } );


    }

    public static function addTranslation( Translations_SegmentTranslationStruct $translation_struct ) {

        $keys_to_insert = [
                'id_segment',
                'id_job',
                'status',
                'time_to_edit',
                'translation',
                'serialized_errors_list',
                'suggestion_position',
                'warning',
                'translation_date',
                'version_number',
                'autopropagated_from'
        ] ;

        $translation = $translation_struct->toArray( $keys_to_insert );
        $fields = array_keys( $translation );
        $bind_keys = [];
        $bind_values = [];


        foreach ( $translation as $key => $val ) {

            $bind_keys[]   = ':' . $key;

            if (
                    strtolower( $val ) == 'now()' ||
                    strtolower( $val ) == 'current_timestamp()' ||
                    strtolower( $val ) == 'sysdate()'
            ) {
                $bind_values[ $key ] = date( "Y-m-d H:i:s" );
            } elseif ( strtolower( $val ) == 'null' ) {
                $bind_values[ $key ] = null;
            } else {
                $bind_values[ $key ] = $val;
            }

        }

        $query = "INSERT INTO `segment_translations` (" . implode( ", ", $fields ) . ") 
                VALUES (" . implode( ", ", $bind_keys ) . ")
				ON DUPLICATE KEY UPDATE
				status = :status,
                suggestion_position = :suggestion_position,
                serialized_errors_list = :serialized_errors_list,
                time_to_edit = :time_to_edit + VALUES( time_to_edit ),
                translation = :translation,
                translation_date = :translation_date,
                warning = :warning
                ";

        if ( array_key_exists( 'version_number', $translation ) ) {
            $query .= ", version_number = :version_number";
        }

        if ( isset( $translation[ 'autopropagated_from' ] ) ) {
            $query .= ", autopropagated_from = NULL";
        }

        if ( empty( $translation[ 'translation' ] ) && !is_numeric( $translation[ 'translation' ] ) ) {
            $msg = "Error setTranslationUpdate. Empty translation found." . var_export( $_POST, true );
            Log::doJsonLog( $msg );
            throw new PDOException( $msg );
        }

        $db = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );

        try {
            $stmt->execute( $bind_values );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );
            throw new PDOException( "Error when (UPDATE) the translation for the segment {$translation['id_segment']} - Error: {$e->getCode()}" );
        }

        return $stmt->rowCount();

    }

    public static function getUpdatedTranslations( $timestamp, $first_segment, $last_segment, $id_job ) {

        $query = "SELECT 
            id_segment as sid, 
            status,
            translation 
        FROM segment_translations
		WHERE
		    id_segment BETWEEN :first_segment AND :last_segment
		AND translation_date > FROM_UNIXTIME( :timestamp )
		AND id_job = :id_job";

        $db      = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( [
                'timestamp'     => $timestamp,
                'first_segment' => $first_segment,
                'last_segment'  => $last_segment,
                'id_job'        => $id_job
        ] );

        return $stmt->fetchAll();

    }

    /**
     * @param Jobs_JobStruct $jStruct
     *
     * @return array
     */
    public static function getMaxSegmentIdsFromJob( Jobs_JobStruct $jStruct ){

        $conn = Database::obtain()->getConnection();

        //Works on the basis that MAX( id_segment ) is the same for ALL Jobs in the same Project
        // furthermore, we need a random ID so, don't worry about MySQL stupidity on random MAX
        //example: http://dev.mysql.com/doc/refman/5.0/en/example-maximum-column-group-row.html
        $select_max_id = "
			SELECT MAX(id_segment) as id_segment
			FROM segment_translations
			JOIN jobs ON id_job = id AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
			WHERE id = :id_job
			GROUP BY id_job, password
		";

        $stmt = $conn->prepare( $select_max_id );
        $stmt->setFetchMode( PDO::FETCH_NUM );
        $stmt->execute( [ 'id_job' => $jStruct->id ] );

        $values = $stmt->fetchAll();
        $_list = [];
        foreach( $values as $row ){
            $_list[] = $row[ 0 ];
        }

        return $_list;

    }

    /**
     * @param Jobs_JobStruct $jStruct
     *
     * @return mixed
     */
    public static function getMinSegmentIdsFromJob( Jobs_JobStruct $jStruct ){

        $conn = Database::obtain()->getConnection();

        //Works on the basis that MAX( id_segment ) is the same for ALL Jobs in the same Project
        // furthermore, we need a random ID so, don't worry about MySQL stupidity on random MAX
        //example: http://dev.mysql.com/doc/refman/5.0/en/example-maximum-column-group-row.html
        $select_max_id = "
			SELECT MIN(id_segment) as id_segment
			FROM segment_translations
			JOIN jobs ON id_job = id AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
			WHERE id = :id_job
			GROUP BY id_job, password
		";

        $stmt = $conn->prepare( $select_max_id );
        $stmt->setFetchMode( PDO::FETCH_NUM );
        $stmt->execute( [ 'id_job' => $jStruct->id ] );

        $values = $stmt->fetchAll();
        $_list = [];
        foreach( $values as $row ){
            $_list[] = $row[ 0 ];
        }

        return $_list;

    }

    public static function updateFirstTimeOpenedContribution( $data, $where ){
        self::updateFields( $data, $where );
    }

    /**
     * Copies the segments.segment field into segment_translations.translation
     * and sets the segment status to <b>DRAFT</b>.
     * This operation is made only for the segments in <b>NEW</b> status
     *
     * @param Jobs_JobStruct $jStruct
     *
     * @return
     */
    public static function copyAllSourceToTargetForJob( Jobs_JobStruct $jStruct ) {

        $query = "UPDATE segment_translations st
                    JOIN segments s ON st.id_segment = s.id
                    JOIN jobs j ON st.id_job = j.id
                      SET st.translation = s.segment, st.status = 'DRAFT', st.translation_date = now()
                    WHERE st.status = 'NEW'
                    AND j.id = :job_id
                    AND j.password = :password
                    AND st.id_segment between :job_first_segment and :job_last_segment";

        $db = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );
        $stmt->execute( [
                'job_id'            => $jStruct->id,
                'password'          => $jStruct->password,
                'job_first_segment' => $jStruct->job_first_segment,
                'job_last_segment'  => $jStruct->job_last_segment
        ] );

        return $stmt->rowCount();
    }

    /**
     * This function propagates the translation to every identical sources in the chunk/job
     *
     * @param                        $params
     * @param                        $job_data
     * @param                        $_idSegment
     *
     * @param Projects_ProjectStruct $project
     *
     * @return array
     * @throws Exception
     */
    public static function propagateTranslation( $params, $job_data, $_idSegment, Projects_ProjectStruct $project ) {

        $db = Database::obtain();

        if ( $project->getWordCountType() == Projects_MetadataDao::WORD_COUNT_RAW ) {
            $sum_sql = "SUM( segments.raw_word_count )";
        } else {
            $sum_sql = "SUM( IF( match_type != 'ICE', eq_word_count, segments.raw_word_count ) )";
        }

        /**
         * We want to avoid that a translation overrides a propagation,
         * so we have to set an additional status when the requested status to propagate is TRANSLATE
         */
        $additional_status = '';
        if ( $params[ 'status' ] == Constants_TranslationStatus::STATUS_TRANSLATED ) {
            $additional_status = "AND status != '" . Constants_TranslationStatus::STATUS_APPROVED . "' ";
        }

        /**
         * Sum the word count grouped by status, so that we can later update the count on jobs table.
         * We only count segments with status different than the current, because we don't need to update
         * the count for the same status.
         *
         */
        $queryTotals = "
           SELECT $sum_sql as total, COUNT(id_segment)as countSeg, status

           FROM segment_translations
              INNER JOIN  segments
              ON segments.id = segment_translations.id_segment
           WHERE id_job = :id_job 
           AND segment_translations.segment_hash = :segment_hash
           AND id_segment BETWEEN :job_first_segment AND :job_last_segment
           AND id_segment != :id_segment
           AND status != :status
           $additional_status
           GROUP BY status
    ";

        try {

            $stmt = $db->getConnection()->prepare( $queryTotals );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job'            => $params[ 'id_job' ],
                    'segment_hash'      => $params[ 'segment_hash' ],
                    'job_first_segment' => $job_data[ 'job_first_segment' ],
                    'job_last_segment'  => $job_data[ 'job_last_segment' ],
                    'id_segment'        => $_idSegment,
                    'status'            => $params[ 'status' ]
            ] );
            $totals = $stmt->fetchAll();

        } catch ( PDOException $e ) {
            throw new Exception( "Error in counting total words for propagation: " . $e->getCode() . ": " . $e->getMessage()
                    . "\n" . $queryTotals . "\n" . var_export( $params, true ),
                    -$e->getCode() );
        }

        $dao = new Translations_SegmentTranslationDao();
        try {
            $segmentsForPropagation = $dao->getSegmentsForPropagation( [
                    'id_segment'        => $_idSegment,
                    'job_first_segment' => $job_data[ 'job_first_segment' ],
                    'job_last_segment'  => $job_data[ 'job_last_segment' ],
                    'segment_hash'      => $params[ 'segment_hash' ],
                    'id_job'            => $params[ 'id_job' ]
            ], $params[ 'status' ] );
        } catch ( PDOException $e ) {
            throw new Exception(
                    sprintf( "Error in querying segments for propagation: %s: %s ", $e->getCode(), $e->getMessage() ),
                    -$e->getCode()
            );
        }

        $propagated_ids = [];

        if ( !empty( $segmentsForPropagation ) ) {

            $propagated_ids = array_map( function ( Translations_SegmentTranslationStruct $translation ) {
                return $translation->id_segment;
            }, $segmentsForPropagation );

            try {

                $place_holders_fields = [];
                foreach ( $params as $key => $value ) {
                    $place_holders_fields[] = "$key = ?";
                }
                $place_holders_fields = implode( ",", $place_holders_fields );
                $place_holders_id     = implode( ',', array_fill( 0, count( $propagated_ids ), '?' ) );

                $values = array_merge(
                        array_values( $params ),
                        [ $params[ 'id_job' ] ],
                        $propagated_ids
                );

                $propagationSql = "
                  UPDATE segment_translations SET $place_holders_fields
                  WHERE id_job = ? AND id_segment IN ( $place_holders_id )
            ";

                $pdo  = $db->getConnection();
                $stmt = $pdo->prepare( $propagationSql );

                $stmt->execute( $values );

            } catch ( PDOException $e ) {
                throw new Exception( "Error in propagating Translation: " . $e->getCode() . ": " . $e->getMessage()
                        . "\n" .
                        $propagationSql
                        . "\n"
                        . var_export( $params, true )
                        . "\n"
                        . var_export( $propagated_ids, true )
                        . "\n",
                        -$e->getCode() );
            }

        }

        return [ 'totals' => $totals, 'propagated_ids' => $propagated_ids ];
    }

    public static function getLastSegmentIDs( $id_job ) {

        // Force Index guarantee that the optimizer will not choose translation_date and scan the full table for new jobs.
        $query = "
		SELECT id_segment
            FROM segment_translations FORCE INDEX (id_job) 
            WHERE id_job = :id_job
            AND `status` IN ( 'TRANSLATED', 'APPROVED' )
            ORDER BY translation_date DESC LIMIT 10
		";

        $db = Database::obtain();
        try {
            //sometimes we can have broken projects in our Database that are not related to a job id
            //the query that extract the projects info returns a null job id for these projects, so skip the exception
            $stmt = $db->getConnection()->prepare( $query );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job' => $id_job
            ] );

            $results = [];
            while( $row = $stmt->fetch() ){
                $results[] = $row[ 'id_segment' ];
            }

        } catch ( Exception $e ) {
            $results = null;
        }

        return $results;
    }

    public static function getEQWLastHour( $id_job, $estimation_seg_ids ) {

        /**
         * If the translator translated the last ten segments in less than 1 hour
         * In the cattool there will be the calculation of word per hour in the footer bar
         *
         */
        $query = "
            SELECT SUM(IF(Ifnull(st.eq_word_count, 0) = 0, raw_word_count,
                   st.eq_word_count)),
                   Min(translation_date),
                   Max(translation_date),
                   IF(Unix_timestamp(Max(translation_date)) - Unix_timestamp(Min(translation_date)) > 3600
                      OR Count(*) < 10, 0, 1) AS data_validity,

                   Round(SUM(IF(Ifnull(st.eq_word_count, 0) = 0, raw_word_count,
                         st.eq_word_count)) /
                               ( Unix_timestamp(Max(translation_date)) -
                                 Unix_timestamp(Min(translation_date)) ) * 3600) AS words_per_hour,
                   Count(*)
            FROM   segment_translations st
            JOIN   segments ON id = st.id_segment
            WHERE  status IN ( 'TRANSLATED', 'APPROVED' )
                   AND id_job = ?
                   AND id_segment IN ( " . implode( ",", array_fill( 0, count( $estimation_seg_ids ), '?' ) ) . " )
    ";

        $db      = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( array_merge( [ $id_job ], $estimation_seg_ids ) );
        $results = $stmt->fetchAll();

        return $results;
    }


}
