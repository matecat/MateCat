<?php

use API\V2\Json\Propagation as PropagationApi;
use Autopropagation\PropagationAnalyser;
use DataAccess\ShapelessConcreteStruct;
use Features\TranslationVersions\VersionHandlerInterface;
use Search\ReplaceEventStruct;

class Translations_SegmentTranslationDao extends DataAccess_AbstractDao {

    const TABLE = "segment_translations";

    /**
     * @var array
     */
    public static $primary_keys = [
            'id_job',
            'id_segment'
    ];

    /**
     * @param     $id_segment
     * @param     $id_job
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

        /**
         * @var $result Translations_SegmentTranslationStruct[]
         */
        $result = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Translations_SegmentTranslationStruct(), [
                'id_job'     => $id_job,
                'id_segment' => $id_segment
        ] );

        return !empty( $result ) ? $result[ 0 ] : null;
    }

    /**
     * @param $segmentIdList
     * @param $date
     *
     * @return array
     */
    public static function updateLastTranslationDateByIdList( $segmentIdList, $date ) {

        if ( false === empty( $segmentIdList ) ) {
            $places = rtrim( str_repeat( " ?,", count( $segmentIdList ) ), "," );

            $conn   = Database::obtain()->getConnection();
            $query  = "UPDATE segment_translations SET translation_date = ? WHERE id_segment IN( $places )";
            $stmt   = $conn->prepare( $query );
            $values = array_merge( [ $date ], $segmentIdList );
            $stmt->execute( $values );
        }
    }

    protected function getSegmentsForPropagation( $params, $status = Constants_TranslationStatus::STATUS_TRANSLATED ) {

        $selectSegmentsToPropagate = " SELECT * FROM segment_translations " .
                " WHERE id_job = :id_job " .
                " AND segment_hash = :segment_hash " .
                " AND id_segment BETWEEN :job_first_segment AND :job_last_segment " .
                " AND id_segment <> :id_segment ; ";

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

    protected function _buildResult( $array_result ) {}

    /**
     * @param Translations_SegmentTranslationStruct $struct
     * @param                                       $severity
     *
     * @return int
     */
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

        $stmt->execute( [
                'id_segment'    => $data[ 'id_segment' ],
                'id_job'        => $data[ 'id_job' ],
                'segment_hash'  => $data[ 'segment_hash' ],
                'edit_distance' => $data[ 'edit_distance' ]
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param $data
     *
     * @return int
     */
    public static function updateEditDistanceForPropagation( $data ) {

        $sql = "UPDATE segment_translations
            SET edit_distance = :edit_distance
              WHERE id_segment <> :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash         ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_segment'    => $data[ 'id_segment' ],
                'id_job'        => $data[ 'id_job' ],
                'segment_hash'  => $data[ 'segment_hash' ],
                'edit_distance' => $data[ 'edit_distance' ]
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param $data
     *
     * @return float|int
     */
    public static function setAnalysisValue( $data ) {

        $where = [
                "id_segment" => $data[ 'id_segment' ],
                "id_job"     => $data[ 'id_job' ]
        ];

        $db = Database::obtain();

        return $db->update( 'segment_translations', $data, $where );
    }

    public static function getUnchangeableStatus( Chunks_ChunkStruct $chunk, $segments_ids, $status, $source_page ) {

        $where_values = [];
        $conn         = Database::obtain()->getConnection();
        $and_ste      = '';

        if ( $status == Constants_TranslationStatus::STATUS_APPROVED || $status == Constants_TranslationStatus::STATUS_APPROVED2 ) {
            /**
             * if source_page is null, we keep the default behaviour and only allow TRANSLATED and APPROVED segments.
             */
            $where_values[] = Constants_TranslationStatus::STATUS_TRANSLATED;
            $where_values[] = Constants_TranslationStatus::STATUS_APPROVED;
            $where_values[] = Constants_TranslationStatus::STATUS_APPROVED2;
        } elseif ( $status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
            /**
             * When status is TRANSLATED we can change APPROVED DRAFT and NEW statuses
             */
            $where_values[] = Constants_TranslationStatus::STATUS_DRAFT;
            $where_values[] = Constants_TranslationStatus::STATUS_NEW;
            $where_values[] = Constants_TranslationStatus::STATUS_TRANSLATED;
            $where_values[] = Constants_TranslationStatus::STATUS_APPROVED;
            $where_values[] = Constants_TranslationStatus::STATUS_APPROVED2;
        } else {
            throw new Exception( 'not allowed to change status to ' . $status );
        }

        $status_placeholders       = str_repeat( '?,', count( $where_values ) - 1 ) . '?';
        $segments_ids_placeholders = str_repeat( '?,', count( $segments_ids ) - 1 ) . '?';

        if ( !is_null( $source_page ) ) {
            /**
             * If source page is being provided, we must return as un-changeable, segments which
             * are currently in the same revision stage as the input source page. To do so, we JOIN
             * segment_translation_events table.
             */
            $join_ste = "LEFT JOIN segment_translation_events ste
                      ON ste.id_segment = st.id_segment
                          AND ste.id_job = ?
                          AND ste.status IN ( ?, ? )
                          AND ste.final_revision = 1 ";

            $where_values = array_merge( [
                    $chunk->id,
                    Constants_TranslationStatus::STATUS_APPROVED,
                    Constants_TranslationStatus::STATUS_APPROVED2
            ], $where_values );
        } else {
            $join_ste = '';
        }

        $sql = "SELECT st.id_segment
                    FROM segment_translations st

                    $join_ste

                    WHERE
                    (

                      st.status NOT IN ( $status_placeholders ) OR

                      translation IS NULL OR
                      translation = ''
                    ) AND st.id_segment IN ( $segments_ids_placeholders )
                    AND st.id_job = ?
                    GROUP BY st.id_segment
                    ";

        $where_values = array_merge( $where_values, $segments_ids );
        $where_values[] = $chunk->id;
        $stmt         = $conn->prepare( $sql );

        $stmt->execute( $where_values );

        return $stmt->fetchAll( PDO::FETCH_FUNC, function ( $id_segment ) {
            return (int)$id_segment;
        } );
    }

    /**
     * @param Translations_SegmentTranslationStruct $translation_struct
     * @param                                       $is_revision
     *
     * @return int
     * @throws ReflectionException
     * @throws PDOException
     */
    public static function addTranslation( Translations_SegmentTranslationStruct $translation_struct, $is_revision ) {

        // avoid version_number null error
        if($translation_struct->version_number === null){
            $translation_struct->version_number = 0;
        }

        $keys_to_insert = [
                'id_segment',
                'id_job',
                'status',
                'translation',
                'serialized_errors_list',
                'suggestions_array',
                'suggestion',
                'suggestion_position',
                'suggestion_source',
                'suggestion_match',
                'warning',
                'translation_date',
                'version_number',
                'autopropagated_from',
                'time_to_edit'
        ];

        $translation = $translation_struct->toArray( $keys_to_insert );

        if ( $is_revision ) {
            $translation[ 'time_to_edit' ] = 0;
        }

        $fields      = array_keys( $translation );
        $bind_keys   = [];
        $bind_values = [];

        foreach ( $translation as $key => $val ) {
            $bind_keys[] = ':' . $key;

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
			    suggestions_array = :suggestions_array,
                suggestion = :suggestion,
                suggestion_position = :suggestion_position,
                suggestion_source = :suggestion_source,
                suggestion_match = :suggestion_match,
                serialized_errors_list = :serialized_errors_list,
                time_to_edit = time_to_edit + VALUES( time_to_edit ),
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
            throw new PDOException( $msg );
        }

        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );

        try {
            $stmt->execute( $bind_values );
        } catch ( PDOException $e ) {
            throw new PDOException( "Error when (UPDATE) the translation for the segment {$translation['id_segment']} - Error: {$e->getCode()}" );
        }

        return $stmt->rowCount();
    }

    /**
     * @param Translations_SegmentTranslationStruct $translation_struct
     *
     * @return int
     * @throws Exception
     */
    public static function updateTranslationAndStatusAndDate( Translations_SegmentTranslationStruct $translation_struct ) {

        $values = [
                'translation',
                'status',
                'translation_date',
        ];

        // persist the version_number in case $translation_struct has already the property hydrated
        if ( null !== $translation_struct->version_number ) {
            $values[] = 'version_number';
        }

        return Translations_SegmentTranslationDao::updateStruct( $translation_struct, $values ) ;

    }

    /**
     * @param $id_job
     * @param $password
     * @param $source_page
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getSegmentTranslationsModifiedByRevisorWithIssueCount( $id_job, $password, $source_page ) {

        $query = "
            select ste.id_segment, j.id, count(distinct qa.id) as q_count
            from segment_translation_events ste  
            
            left join qa_entries qa ON ( qa.id_job, qa.id_segment ) = ( ste.id_job ,ste.id_segment )
            join jobs j ON j.id = ste.id_job
            join segment_translations  st on st.id_segment = ste.id_segment and st.id_job = ste.id_job
            join segment_translation_versions  stv on stv.id_segment = ste.id_segment and stv.id_job = ste.id_job
            
            join (select ste.id_segment, max(ste.version_number) as max_v
            from segment_translation_events  ste 
            join jobs j ON j.id = ste.id_job
            where j.id = :id_job and j.password = :password
             
            and ste.status = 'TRANSLATED'
            group by ste.id_segment) as tra
            on tra.id_segment = ste.id_segment
            
            join (select ste.id_segment, max(ste.version_number) as max_v
            from segment_translation_events  ste 
            join jobs j ON j.id = ste.id_job
            where j.id = :id_job and j.password = :password
            
            and ste.status = 'APPROVED'
            group by ste.id_segment) as r1
            on r1.id_segment = ste.id_segment
            
            where j.id = :id_job and j.password = :password
            
            and r1.max_v != tra.max_v
            and st.translation != stv.translation
            and qa.deleted_at is null
            and ste.source_page = :source_page
            group by ste.id_segment;";

        $stmt = $this->_getStatementForCache( $query );

        return $this->_fetchObject( $stmt,
                new ShapelessConcreteStruct(),
                [
                        'id_job'      => $id_job,
                        'password'    => $password,
                        'source_page' => $source_page,
                ]
        );
    }

    /**
     * @param Jobs_JobStruct $jStruct
     *
     * @return array
     */
    public static function getMaxSegmentIdsFromJob( Jobs_JobStruct $jStruct ) {

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
        $_list  = [];
        foreach ( $values as $row ) {
            $_list[] = $row[ 0 ];
        }

        return $_list;
    }

    /**
     * @param $data
     * @param $where
     */
    public static function updateFirstTimeOpenedContribution( $data, $where ) {
        self::updateFields( $data, $where );
    }

    /**
     * This function propagates the translation to every identical sources in the chunk/job
     *
     * @param Translations_SegmentTranslationStruct $segmentTranslationStruct
     * @param Chunks_ChunkStruct                    $chunkStruct
     * @param                                       $_idSegment
     * @param Projects_ProjectStruct                $project
     *
     * @param VersionHandlerInterface               $versionHandler
     * @param bool                                  $execute_update
     * @param bool                                  $persistPropagatedVersions
     *
     * <code>
     *      $propagationTotal = [
     *          'totals'                   => [
     *              'total'    => null,
     *              'countSeg' => null,
     *              'status'   => null
     *          ],
     *          'propagated_ids'           => [],
     *          'segments_for_propagation' => Translations_SegmentTranslationStruct[]
     *      ];
     *  </code>
     *
     * @return array
     * @throws Exception
     */
    public static function propagateTranslation(
            Translations_SegmentTranslationStruct $segmentTranslationStruct,
            Chunks_ChunkStruct $chunkStruct,
            $_idSegment,
            Projects_ProjectStruct $project,
            VersionHandlerInterface $versionHandler,
            $execute_update = true
    ) {
        $db = Database::obtain();

        if ( $project->getWordCountType() == Projects_MetadataDao::WORD_COUNT_RAW ) {
            $sum_sql = "SUM( segments.raw_word_count )";
        } else {
            $sum_sql = "SUM( IF( match_type != 'ICE', eq_word_count, segments.raw_word_count ) )";
        }

        /**
         * Sum the word count grouped by status, so that we can later update the count on jobs table.
         * We only count segments with status different from the current, because we don't need to update
         * the count for the same status.
         *
         */
        $queryTotals = "
           SELECT $sum_sql as total, sum(1) as countSeg, segment_translations.*

           FROM segment_translations
              INNER JOIN  segments
              ON segments.id = segment_translations.id_segment
           WHERE id_job = :id_job 
           AND segment_translations.segment_hash = :segment_hash
           AND id_segment BETWEEN :job_first_segment AND :job_last_segment
           AND id_segment != :id_segment
           GROUP BY id_segment with rollup
        ";

        try {

            $stmt = $db->getConnection()->prepare( $queryTotals );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job'            => $segmentTranslationStruct[ 'id_job' ],
                    'segment_hash'      => $segmentTranslationStruct[ 'segment_hash' ],
                    'job_first_segment' => $chunkStruct[ 'job_first_segment' ],
                    'job_last_segment'  => $chunkStruct[ 'job_last_segment' ],
                    'id_segment'        => $_idSegment,
            ] );

            $recordNum                            = 0;
            $_recordIteratorIdx                   = 1;
            $lastRow                              = null;
            $arrayOfSegmentTranslationToPropagate = $stmt->fetchAll( PDO::FETCH_FUNC, function () use ( $stmt, &$recordNum, &$_recordIteratorIdx, &$lastRow ) {

                $args = func_get_args();

                if ( empty( $recordNum ) ) {
                    $recordNum = $stmt->rowCount();
                }

                if ( $recordNum == $_recordIteratorIdx ) {
                    $lastRow = $args;
                } else {
                    $_recordIteratorIdx++;

                    $raw_values = array_slice( $args, 2 );

                    $array_values = [
                            'id_segment'            => $raw_values[ 0 ],
                            'id_job'                => $raw_values[ 1 ],
                            'segment_hash'          => $raw_values[ 2 ],
                            'autopropagated_from'   => $raw_values[ 3 ],
                            'status'                => $raw_values[ 4 ],
                            'translation'           => $raw_values[ 5 ],
                            'translation_date'      => $raw_values[ 6 ],
                            'time_to_edit'          => $raw_values[ 7 ],
                            'match_type'            => $raw_values[ 8 ],
                            'context_hash'          => $raw_values[ 9 ],
                            'eq_word_count'         => $raw_values[ 10 ],
                            'standard_word_count'   => $raw_values[ 11 ],
                            'suggestions_array'     => $raw_values[ 12 ],
                            'suggestion'            => $raw_values[ 13 ],
                            'suggestion_match'      => $raw_values[ 14 ],
                            'suggestion_source'     => $raw_values[ 15 ],
                            'suggestion_position'   => $raw_values[ 16 ],
                            'mt_qe'                 => $raw_values[ 17 ],
                            'tm_analysis_status'    => $raw_values[ 18 ],
                            'locked'                => $raw_values[ 19 ],
                            'warning'               => $raw_values[ 20 ],
                            'serialized_error_list' => $raw_values[ 21 ],
                            'version_number'        => $raw_values[ 22 ],
                    ];

                    return new Translations_SegmentTranslationStruct( $array_values );
                }
            } );

            array_pop( $arrayOfSegmentTranslationToPropagate );

            if($lastRow !== null and is_array($lastRow)){
                $propagationAnalyser = new PropagationAnalyser();
                $propagationTotal    = $propagationAnalyser->analyse( $segmentTranslationStruct, $arrayOfSegmentTranslationToPropagate );
                $propagationTotal->setTotals( [
                    'propagated_ice_total'     => $propagationAnalyser->getPropagatedIceCount(),
                    'not_propagated_total'     => $propagationAnalyser->getNotPropagatedCount(),
                    'propagated_total'         => $propagationAnalyser->getPropagatedCount(),
                    'not_propagated_ice_total' => $propagationAnalyser->getNotPropagatedIceCount(),
                    'total'                    => $lastRow[ 0 ],
                    'countSeg'                 => $lastRow[ 1 ],
                    'status'                   => $lastRow[ 2 ],
                ] );
            }


        } catch ( PDOException $e ) {
            throw new Exception( "Error in counting total words for propagation: " . $e->getCode() . ": " . $e->getMessage()
                    . "\n" . $queryTotals . "\n" . var_export( $segmentTranslationStruct, true ),
                    -$e->getCode() );
        }

        if ( isset($propagationTotal) and $propagationTotal !== null and !empty( $propagationTotal->getTotals() ) ) {

            if ( true === $execute_update and !empty( $propagationTotal->getSegmentsForPropagation() ) ) {

                try {

                    $place_holders_fields = [];
                    $field_values         = [];
                    foreach ( $segmentTranslationStruct as $key => $value ) {
                        if ( is_null( $value ) ) {
                            continue;
                        }

                        // UPDATE ONLY THIS FIELDS
                        $fields_to_update = [
                            'translation',
                            'version_number',
                            'status',
                            'translation_date',
                            'autopropagated_from',
                            'serialized_errors_list',
                            'warning',
                        ];

                        if(in_array($key, $fields_to_update)){
                            $place_holders_fields[] = "$key = ?";
                            $field_values[]         = $value;
                        }
                    }

                    $place_holders_fields = implode( ",", $place_holders_fields );
                    $place_holders_id     = implode( ',', array_fill( 0, count( $propagationTotal->getPropagatedIds() ), '?' ) );

                    if ( false === empty( $place_holders_id ) ) {
                        $values = array_merge(
                                $field_values,
                                [ $segmentTranslationStruct[ 'id_job' ] ]
                        );

                        if ( false === empty( $propagationTotal->getPropagatedIds() ) ) {
                            $values = array_merge(
                                    $values,
                                    $propagationTotal->getPropagatedIds()
                            );
                        }

                        $propagationSql = "
                            UPDATE segment_translations SET $place_holders_fields
                            WHERE id_job = ? AND id_segment IN ( $place_holders_id )
                        ";

                        $pdo  = $db->getConnection();
                        $stmt = $pdo->prepare( $propagationSql );

                        $stmt->execute( $values );

                        // update related versions only if the parent translation has changed
                        if ( false === empty( $propagationTotal->getPropagatedIdsToUpdateVersion() ) ) {
                            $versionHandler->savePropagationVersions(
                                    $segmentTranslationStruct,
                                    $propagationTotal->getPropagatedIdsToUpdateVersion()
                            );
                        }
                    }
                } catch ( PDOException $e ) {
                    throw new Exception( "Error in propagating Translation: " . $e->getCode() . ": " . $e->getMessage()
                            . "\n" .
                            $propagationSql
                            . "\n"
                            . var_export( $segmentTranslationStruct, true )
                            . "\n"
                            . var_export( $propagationTotal->getPropagatedIds(), true )
                            . "\n",
                            -$e->getCode() );
                }
            }
        }

        if(!isset($propagationTotal)){
            $propagationTotal = new Propagation_PropagationTotalStruct();
        }

        return ( new PropagationApi( $propagationTotal ) )->render();
    }

    /**
     * Select last 10 translated segments in the last hour
     *
     * @param $id_job
     *
     * @return array|null
     */
    public static function getLast10TranslatedSegmentIDsInLastHour( $id_job ) {

        // temporal interval of 1 hour
        $now   = new DateTime();
        $limit = new DateTime( '-1 hour' );

        // Force Index guarantee that the optimizer will not choose translation_date and scan the full table for new jobs.
        $query = "
		SELECT id_segment
            FROM segment_translations FORCE INDEX (id_job) 
            WHERE id_job = :id_job
            AND `status` IN ( 'TRANSLATED', 'APPROVED', 'APPROVED2' )
            AND `translation_date` <= :now AND `translation_date` >= :limit
            ORDER BY translation_date DESC LIMIT 10
		";

        $db = Database::obtain();
        try {
            // Sometimes there could be broken projects that are not related to a job ID.
            // The query that extracts the project info returns a null job ID for these projects, so skip the exception.
            $stmt = $db->getConnection()->prepare( $query );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job' => $id_job,
                    'limit'  => $limit->format( 'Y-m-d H:i:s' ),
                    'now'    => $now->format( 'Y-m-d H:i:s' ),
            ] );

            $results = [];
            while ( $row = $stmt->fetch() ) {
                $results[] = $row[ 'id_segment' ];
            }

        } catch ( Exception $e ) {
            $results = null;
        }

        return $results;
    }

    /**
     * @param $id_job
     * @param $estimation_seg_ids
     *
     * @return array
     */
    public static function getWordsPerSecond( $id_job, $estimation_seg_ids ) {

        /**
         * If the translator translated the last ten segments in less than 1 hour
         * In the cattool there will be the calculation of word per hour in the footer bar
         *
         */
        $query = "
            SELECT 
                   Round( 
                        SUM( s.raw_word_count ) / ( Unix_timestamp(Max(translation_date)) - Unix_timestamp(Min(translation_date)) )
                   ) AS words_per_second
            
            FROM   segment_translations st
            JOIN   segments s ON id = st.id_segment
            WHERE  status IN ( 'TRANSLATED', 'APPROVED', 'APPROVED2' )
                   AND id_job = ?
                   AND id_segment IN ( " . implode( ",", array_fill( 0, count( $estimation_seg_ids ), '?' ) ) . " )
    ";

        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( array_merge( [ $id_job ], $estimation_seg_ids ) );

        return $stmt->fetchAll();
    }

    /**
     * @param $events
     *
     * @return int
     */
    public static function rebuildFromReplaceEvents( $events ) {

        $conn          = Database::obtain()->getConnection();
        $affected_rows = 0;

        $conn->beginTransaction();

        /** @var ReplaceEventStruct $result */
        foreach ( $events as $result ) {
            try {
                $query = "UPDATE segment_translations SET translation = :translation WHERE id_job=:id_job AND id_segment=:id_segment";
                $stmt  = $conn->prepare( $query );

                $params = [
                        ':id_job'      => $result->id_job,
                        ':id_segment'  => $result->id_segment,
                        ':translation' => $result->translation_after_replacement
                ];

                $stmt->execute( $params );

                $affected_rows++;
            } catch ( \Exception $e ) {
                $conn->rollBack();
                $affected_rows = 0;
            }
        }

        $conn->commit();

        return $affected_rows;
    }

    /**
     * @param $id_segment
     * @param $suggestions
     */
    public static function updateSuggestionsArray($id_segment, $suggestions) {

        if(empty($suggestions)){
            return;
        }

        $conn  = Database::obtain()->getConnection();
        $query = "UPDATE segment_translations SET suggestions_array = :suggestions_array WHERE id_segment=:id_segment";

        $stmt  = $conn->prepare( $query );
        $suggestions_array = json_encode($suggestions);

        $params = [
            'id_segment'        => $id_segment,
            'suggestions_array' => $suggestions_array,
        ];

        $stmt->execute( $params );
    }
}
