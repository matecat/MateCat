<?php

namespace Model\Translations;

use DateTime;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FileStruct;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectStruct;
use Model\Propagation\PropagationTotalStruct;
use Model\Search\ReplaceEventStruct;
use PDO;
use PDOException;
use ReflectionException;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\PropagationWorker;
use Utils\Autopropagation\PropagationAnalyser;
use Utils\Constants\SegmentSize;
use Utils\Constants\TranslationStatus;
use View\API\V2\Json\Propagation as PropagationApi;

class SegmentTranslationDao extends AbstractDao {

    const TABLE = "segment_translations";

    /**
     * @var array
     */
    public static array $primary_keys = [
            'id_job',
            'id_segment'
    ];

    /**
     * @param array $id_list
     * @param int   $jobId
     * @param int   $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getAllSegmentsByIdListAndJobId( array $id_list, int $jobId, int $ttl = 0 ): array {

        $chunked_id_list = array_chunk( $id_list, 20, true );
        $resultSet       = [];

        foreach ( $chunked_id_list as $list ) {

            $sql = "SELECT * FROM " . static::TABLE . " WHERE id_segment IN( " . implode( ',', array_fill( 0, count( $list ), '?' ) ) . " ) AND id_job = ? ;";

            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare( $sql );

            $thisDao = new self();

            /**
             * @var $result SegmentTranslationStruct[]
             */
            $result = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt,
                    SegmentTranslationStruct::class,
                    array_merge( $list, [ $jobId ] )
            );

            $resultSet = array_merge( !empty( $result ) ? $result : [], $resultSet );

        }

        return $resultSet;

    }

    /**
     * @param SegmentTranslationStruct[] $translation_struct
     *
     * @return int
     * @throws Exception
     */
    public static function updateTranslationAndStatusAndDateByList( array $translation_struct ): int {

        $chunked_id_list = array_chunk( $translation_struct, 20, true );
        $tuple_list      = "( ?, ?, ?, ?, ?, ? )"; // the first 2 quotation marks are id_segment and id_job
        $rowCount        = 0;
        $conn            = Database::obtain()->getConnection();

        foreach ( $chunked_id_list as $list ) {

            $tuple_marks = array_fill( 0, count( $list ), $tuple_list );

            $sql = "INSERT INTO " . static::TABLE . " (id_segment, id_job, translation, status, translation_date, version_number) VALUES " . implode( ", ", $tuple_marks )
                    . " ON DUPLICATE KEY UPDATE "
                    . "translation = VALUES(translation), status = VALUES(status), translation_date = VALUES(translation_date), version_number = VALUES(version_number) ;";

            $stmt = $conn->prepare( $sql );

            $values = [];

            foreach ( $list as $row ) {

                if ( strlen( $row->translation ) > SegmentSize::LIMIT ) {
                    throw new PDOException( "Translation size limit reached. Translation is larger than 65kb.", -2 );
                }
                $values[] = $row->id_segment;
                $values[] = $row->id_job;
                $values[] = $row->translation;
                $values[] = $row->status;
                $values[] = $row->translation_date;
                $values[] = $row->version_number;
            }

            $stmt->execute( $values );
            $stmt->closeCursor();

            $rowCount += $stmt->rowCount();

        }

        return $rowCount;

    }

    /**
     * @param int $id_segment
     * @param int $id_job
     * @param int $ttl
     *
     * @return SegmentTranslationStruct
     * @throws ReflectionException
     */
    public static function findBySegmentAndJob( int $id_segment, int $id_job, int $ttl = 0 ): ?SegmentTranslationStruct {

        $conn = Database::obtain()->getConnection();

        $sql = "SELECT * FROM segment_translations WHERE " .
                " id_segment = :id_segment AND " .
                " id_job = :id_job ";

        $stmt = $conn->prepare( $sql );

        $thisDao = new self();

        /**
         * @var $result SegmentTranslationStruct[]
         */
        $result = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, SegmentTranslationStruct::class, [
                'id_job'     => $id_job,
                'id_segment' => $id_segment
        ] );

        return !empty( $result ) ? $result[ 0 ] : null;
    }

    /**
     * @param array  $segmentIdList
     * @param string $date
     */
    public static function updateLastTranslationDateByIdList( array $segmentIdList, string $date ) {

        if ( false === empty( $segmentIdList ) ) {
            $places = rtrim( str_repeat( " ?,", count( $segmentIdList ) ), "," );

            $conn   = Database::obtain()->getConnection();
            $query  = "UPDATE segment_translations SET translation_date = ? WHERE id_segment IN( $places )";
            $stmt   = $conn->prepare( $query );
            $values = array_merge( [ $date ], $segmentIdList );
            $stmt->execute( $values );
        }
    }

    /**
     * @param int $id_job
     *
     * @return SegmentTranslationStruct[]
     */
    public function getByJobId( int $id_job ): array {

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM segment_translations WHERE id_job = ? " );

        $stmt->execute( [ $id_job ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, SegmentTranslationStruct::class );

        return $stmt->fetchAll();
    }

    /**
     * @param FileStruct $file
     *
     * @return SegmentTranslationStruct[]
     */
    public function getByFile( FileStruct $file ): array {

        $sql = "SELECT * FROM segment_translations st " .
                " JOIN segments s on s.id  = st.id_segment AND s.id_file = :id_file " .
                " WHERE s.show_in_cattool = 1 ";

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_file' => $file->id ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, SegmentTranslationStruct::class );

        return $stmt->fetchAll();
    }

    protected function _buildResult( array $array_result ) {
    }

    /**
     * @param array $data
     *
     * @return int
     */
    public static function setAnalysisValue( array $data ): int {

        $query = "UPDATE `segment_translations` SET ";
        foreach ( $data as $key => $value ) {
            $query .= "$key = :$key ,";
        }

        $query = rtrim( $query, "," );
        $query .= "
                WHERE id_segment = :id_segment 
                  AND id_job = :id_job
                  AND tm_analysis_status != 'SKIPPED';";

        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare( $query );

        $stmt->execute( $data );

        return $stmt->rowCount();

    }


    /**
     * @param JobStruct $chunk
     * @param array     $segments_ids
     * @param string    $status
     * @param int|null  $source_page
     *
     * @return array
     * @throws Exception
     */
    public static function getUnchangeableStatus( JobStruct $chunk, array $segments_ids, string $status, ?int $source_page ): array {

        $where_values = [];
        $conn         = Database::obtain()->getConnection();

        if ( $status == TranslationStatus::STATUS_APPROVED || $status == TranslationStatus::STATUS_APPROVED2 ) {
            /**
             * if source_page is null, we keep the default behavior and only allow TRANSLATED and APPROVED segments.
             */
            $where_values[] = TranslationStatus::STATUS_TRANSLATED;
            $where_values[] = TranslationStatus::STATUS_APPROVED;
            $where_values[] = TranslationStatus::STATUS_APPROVED2;
        } elseif ( $status == TranslationStatus::STATUS_TRANSLATED ) {
            /**
             * When status is TRANSLATED we can change APPROVED DRAFT and NEW statuses
             */
            $where_values[] = TranslationStatus::STATUS_DRAFT;
            $where_values[] = TranslationStatus::STATUS_NEW;
            $where_values[] = TranslationStatus::STATUS_TRANSLATED;
            $where_values[] = TranslationStatus::STATUS_APPROVED;
            $where_values[] = TranslationStatus::STATUS_APPROVED2;
        } else {
            throw new Exception( 'not allowed to change status to ' . $status );
        }

        $status_placeholders       = str_repeat( '?,', count( $where_values ) - 1 ) . '?';
        $segments_ids_placeholders = str_repeat( '?,', count( $segments_ids ) - 1 ) . '?';

        if ( !is_null( $source_page ) ) {
            /**
             * If the source page is being provided, we must return as unchangeable, segments which
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
                    TranslationStatus::STATUS_APPROVED,
                    TranslationStatus::STATUS_APPROVED2
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

        $where_values   = array_merge( $where_values, $segments_ids );
        $where_values[] = $chunk->id;
        $stmt           = $conn->prepare( $sql );

        $stmt->execute( $where_values );

        return $stmt->fetchAll( PDO::FETCH_FUNC, function ( $id_segment ) {
            return (int)$id_segment;
        } );
    }

    /**
     * @param SegmentTranslationStruct $translation_struct
     * @param bool                     $is_revision
     *
     * @return int
     */
    public static function addTranslation( SegmentTranslationStruct $translation_struct, bool $is_revision ): int {

        // avoid version_number null error
        $translation_struct->version_number = $translation_struct->version_number ?? 0;

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
                warning = :warning,
                version_number = :version_number,
                autopropagated_from = :autopropagated_from
                ";

        if ( empty( $translation[ 'translation' ] ) && !is_numeric( $translation[ 'translation' ] ) ) {
            $msg = "Error setTranslationUpdate. Empty translation found." . var_export( $_POST, true );
            throw new PDOException( $msg );
        }

        if ( strlen( $translation[ 'translation' ] ) > SegmentSize::LIMIT ) {
            throw new PDOException( "Translation size limit reached. Translation is larger than 65kb.", -2 );
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
     * @param SegmentTranslationStruct $translation_struct
     *
     * @return int
     * @throws Exception
     */
    public static function updateTranslationAndStatusAndDate( SegmentTranslationStruct $translation_struct ): int {

        $values = [
                'fields' => [
                        'translation',
                        'status',
                        'translation_date',
                ]
        ];

        // persist the version_number in case $translation_struct has already the property hydrated
        if ( null !== $translation_struct->version_number ) {
            $values[ 'fields' ][] = 'version_number';
        }

        return SegmentTranslationDao::updateStruct( $translation_struct, $values );

    }

    /**
     * @param int    $id_job
     * @param string $password
     * @param int    $source_page
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public
    function getSegmentTranslationsModifiedByRevisorWithIssueCount( int $id_job, string $password, int $source_page ): array {

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
            
            and (ste.status = 'APPROVED' or ste.status = 'APPROVED2')
            group by ste.id_segment) as r1
            on r1.id_segment = ste.id_segment
            
            where j.id = :id_job and j.password = :password
            
            and r1.max_v != tra.max_v
            and st.translation != stv.translation
            and qa.deleted_at is null
            and ste.source_page = :source_page
            group by ste.id_segment;";

        $stmt = $this->_getStatementForQuery( $query );

        return $this->_fetchObjectMap( $stmt,
                ShapelessConcreteStruct::class,
                [
                        'id_job'      => $id_job,
                        'password'    => $password,
                        'source_page' => $source_page,
                ]
        );
    }

    /**
     * @param JobStruct $jStruct
     *
     * @return array
     */
    public
    static function getMaxSegmentIdsFromJob( JobStruct $jStruct ): array {

        $conn = Database::obtain()->getConnection();

        //Works on the basis that MAX( id_segment ) is the same for ALL Jobs in the same Project
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
    public
    static function updateFirstTimeOpenedContribution( $data, $where ) {
        self::updateFields( $data, $where );
    }

    /**
     * This function propagates the translation to every identical sources in the chunk/job
     *
     * @param SegmentTranslationStruct $segmentTranslationStruct
     * @param JobStruct                $chunkStruct
     * @param int                      $_idSegment
     * @param ProjectStruct            $project
     *
     * @param bool                     $execute_update
     *
     * <code>
     *      $propagationTotal = [
     *          'totals'                   => [
     *              'total'    => null,
     *              'countSeg' => null,
     *              'status'   => null
     *          ],
     *          'propagated_ids'           => [],
     *          'segments_for_propagation' => SegmentTranslationStruct[]
     *      ];
     *  </code>
     *
     * @return array
     * @throws Exception
     */
    public
    static function propagateTranslation(
            SegmentTranslationStruct $segmentTranslationStruct,
            JobStruct $chunkStruct,
            int $_idSegment,
            ProjectStruct $project,
            bool $execute_update = true
    ): array {
        $db = Database::obtain();

        if ( $project->getWordCountType() == MetadataDao::WORD_COUNT_RAW ) {
            $sum_sql = "SUM( segments.raw_word_count )";
        } else {
            $sum_sql = "SUM( IF( match_type != 'ICE', eq_word_count, segments.raw_word_count ) )";
        }

        /**
         * Sum the word counts grouped by status, so that we can later update the count on the job table.
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

                    return null; // this is the last row, we don't need to return a struct for it
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

                    return new SegmentTranslationStruct( $array_values );
                }
            } );

            array_pop( $arrayOfSegmentTranslationToPropagate );

            if ( $lastRow !== null and is_array( $lastRow ) ) {

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

                $propagationObject = [
                        'translationStructTemplate' => $segmentTranslationStruct,
                        'id_segment'                => $_idSegment,
                        'job'                       => $chunkStruct,
                        'project'                   => $project,
                        'propagationAnalysis'       => $propagationTotal,
                        'execute_update'            => $execute_update
                ];

                WorkerClient::enqueue( 'PROPAGATION', PropagationWorker::class, $propagationObject, [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );

            }

        } catch ( PDOException $e ) {
            throw new Exception( "Error in counting total words for propagation: " . $e->getCode() . ": " . $e->getMessage()
                    . "\n" . $queryTotals . "\n" . var_export( $segmentTranslationStruct, true ),
                    -$e->getCode() );
        }


        if ( !isset( $propagationTotal ) ) {
            $propagationTotal = new PropagationTotalStruct();
        }

        return ( new PropagationApi( $propagationTotal ) )->render();
    }

    /**
     * Select the last 10 translated segments in the last hour
     *
     * @param int $id_job
     *
     * @return array|null
     */
    public
    static function getLast10TranslatedSegmentIDsInLastHour( int $id_job ): ?array {

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
     * @param int        $id_job
     * @param array|null $estimation_seg_ids
     *
     * @return array
     */
    public
    static function getWordsPerSecond( int $id_job, ?array $estimation_seg_ids = [] ): array {

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
     * @param array $events
     *
     * @return int
     */
    public
    static function rebuildFromReplaceEvents( array $events ): int {

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
            } catch ( Exception $e ) {
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
    public
    static function updateSuggestionsArray( $id_segment, $suggestions ) {

        if ( empty( $suggestions ) ) {
            return;
        }

        $conn  = Database::obtain()->getConnection();
        $query = "UPDATE segment_translations SET suggestions_array = :suggestions_array WHERE id_segment=:id_segment";

        $stmt              = $conn->prepare( $query );
        $suggestions_array = json_encode( $suggestions );

        $params = [
                'id_segment'        => $id_segment,
                'suggestions_array' => $suggestions_array,
        ];

        $stmt->execute( $params );
    }
}
