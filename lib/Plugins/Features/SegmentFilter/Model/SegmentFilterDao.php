<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:25 AM
 */

namespace Features\SegmentFilter\Model;

use Constants_TranslationStatus;
use DataAccess\ShapelessConcreteStruct;
use Database;
use Features\ReviewExtended\ReviewUtils;
use Features\SecondPassReview;
use Features\SegmentFilter\Model\FilterDefinition;
use Chunks_ChunkStruct;


class SegmentFilterDao extends \DataAccess_AbstractDao {

    /**
     * @param \Chunks_ChunkStruct $chunk
     * @param FilterDefinition    $filter
     *
     * @return array
     */
    public static function findSegmentIdsBySimpleFilter( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {

        if ( $filter->revisionNumber() ) {

            $join_events = " JOIN (
            
                    SELECT id_segment as ste_id_segment, source_page 
                    FROM  segment_translation_events 
                    JOIN ( 
                        SELECT max(id) as _m_id FROM segment_translation_events
                            WHERE id_job = :id_job
                            AND id_segment BETWEEN :job_first_segment AND :job_last_segment
                            GROUP BY id_segment 
                    ) AS X ON _m_id = segment_translation_events.id
                    ORDER BY id_segment
            
                ) ste ON ste.ste_id_segment = st.id_segment AND ste.source_page = :source_page " ;

            $join_data ['source_page' ] = ReviewUtils::revisionNumberToSourcePage( $filter->revisionNumber() );
        }
        else {
            $join_events = "" ;
            $join_data = [] ;
        }

        //
        // Note 2020-01-13
        // --------------------------------
        // We added a UNION to this query to include also the unmodified ICE segments translation in R1
        //
        $sql = "
            select * from ( 
	SELECT st.id_segment AS id
          FROM
           segment_translations st
           JOIN jobs ON jobs.id = st.id_job

               AND jobs.id = :id_job
               AND jobs.password = :password
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               AND st.status = :status
           ".$join_events."
		UNION 
        SELECT st.id_segment AS id
          FROM
           segment_translations st
           JOIN jobs ON jobs.id = st.id_job
               AND jobs.id = :id_job
               AND jobs.password = :password
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               AND st.status = :status
               AND version_number = 0 AND match_type = 'ICE' AND translation_date IS NULL 
 ) as E1 ORDER BY E1.id";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $data = array_merge( [
                'id_job'            => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment'  => $chunk->job_last_segment,
                'password'          => $chunk->password,
                'status'            => $filter->getSegmentStatus()
        ], $join_data );

        $stmt->execute( $data );

        return $stmt->fetchAll();
    }

    /**
     * @param $filter
     *
     * @return object
     */
    private static function __getWhereFromFilter( FilterDefinition $filter ) {
        $where      = '';
        $where_data = [];

        if ( $filter->isFiltered() ) {
            $where      = " AND st.status = :status ";
            $where_data = [ 'status' => $filter->getSegmentStatus() ];

            if ( in_array( $filter->getSegmentStatus(), Constants_TranslationStatus::$REVISION_STATUSES ) ) {
                $where .= " AND ste.source_page = :source_page OR ste.source_page = null " ;
                $where_data[ 'source_page' ] = ReviewUtils::revisionNumberToSourcePage(
                        $filter->revisionNumber()
                );
            }
        }

        if ( $filter->hasCustomCondition() ) {
            $where .= " AND ( " . $filter->getCustomConditionSQL() . " ) " ;
            array_merge( $where_data, $filter->getCustomConditionData() ) ;
        }

        return (object) [ 'sql' => $where, 'data' => $where_data ];
    }


    private static function __getData( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {
        $data = [
                'id_job'            => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment'  => $chunk->job_last_segment,
                'password'          => $chunk->password
        ];

        if ( $filter->getSegmentStatus() ) {
            $data = array_merge( $data, [
                    'status' => $filter->getSegmentStatus()
            ] );
        }

        if ( in_array( $filter->getSegmentStatus(), Constants_TranslationStatus::$REVISION_STATUSES ) ) {
            $data = array_merge ( $data, [ 'source_page' => ReviewUtils::revisionNumberToSourcePage(
                    $filter->revisionNumber()
            ) ] ) ;
        }

        if ( $filter->sampleData() ) {
            switch ( $filter->sampleType() ) {
                case 'repetitions':
//                    $data = array_merge( $data, [
//                            'match_type' => \Constants_SegmentTranslationsMatchType::REPETITIONS,
//                    ] );
                    break;

                case 'mt':
                    $data = array_merge( $data, [
                            'match_type' => \Constants_SegmentTranslationsMatchType::MT,
                    ] );
                    break;

                case 'matches':
                    $data = array_merge( $data, [
                            'match_type_100_public' => \Constants_SegmentTranslationsMatchType::_100_PUBLIC,
                            'match_type_100'        => \Constants_SegmentTranslationsMatchType::_100,
                            'match_type_ice'        => \Constants_SegmentTranslationsMatchType::ICE
                    ] );
                    break;

                case 'fuzzies_50_74':
                    $data = array_merge( $data, [
                            'match_type' => \Constants_SegmentTranslationsMatchType::_50_74,
                    ] );
                    break;

                case 'fuzzies_75_84':
                    $data = array_merge( $data, [
                            'match_type' => \Constants_SegmentTranslationsMatchType::_75_84,
                    ] );
                    break;

                case 'fuzzies_85_94':
                    $data = array_merge( $data, [
                            'match_type' => \Constants_SegmentTranslationsMatchType::_85_94,
                    ] );
                    break;

                case 'fuzzies_95_99':
                    $data = array_merge( $data, [
                            'match_type' => \Constants_SegmentTranslationsMatchType::_95_99,
                    ] );
                    break;

                case 'todo':
                    $data = array_merge( $data, [
                            'status_new'   => Constants_TranslationStatus::STATUS_NEW,
                            'status_draft' => Constants_TranslationStatus::STATUS_DRAFT
                    ] );

                    if ( $chunk->getIsReview() ) {
                        $data = array_merge( $data, [
                                'status_translated'   => Constants_TranslationStatus::STATUS_TRANSLATED,
                        ] );
                    }

                    if ( $chunk->isSecondPassReview() ) {
                        $data = array_merge( $data, [
                                'status_approved'   => Constants_TranslationStatus::STATUS_APPROVED,
                                'source_page'       => $chunk->getSourcePage()
                        ] );
                    }

                    break;
            }

        }

        return $data;
    }

    /**
     * @param $chunk
     * @param $filter
     *
     * @return object
     */
    private static function __getLimit( Chunks_ChunkStruct $chunk, FilterDefinition $filter, $source_page ) {

        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $where = self::__getWhereFromFilter( $filter );

        $countSql = "SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment

           $ste_join

           WHERE 1
           $where->sql ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $countSql );

        $data = self::__getData( $chunk, $filter );

        if ( !empty( $where->data ) ) {
            $data = array_merge( $data, $where->data );
        }

        $stmt->execute( $data );
        $count = $stmt->rowCount();

        if ( $count == 0 ) {
            // TODO: handle case
        }

        $limit = round( ( $count / 100 ) * $filter->sampleSize() );

        return (object)[
                'limit'       => $limit,
                'count'       => $count,
                'sample_size' => $filter->sampleSize()
        ];
    }

    /**
     * @param Chunks_ChunkStruct                             $chunk
     * @param \Features\SegmentFilter\Model\FilterDefinition $filter
     *
     * @return \DataAccess_IDaoStruct[]
     * @throws \Exception
     */
    public static function findSegmentIdsForSample( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {

        $source_page = $chunk->getSourcePage();

        if ( $filter->sampleSize() > 0 ) {
            $limit = self::__getLimit( $chunk, $filter, $source_page );
        } else {
            //initialize limit with 0 in all attributes because we use $limit attributes in methods called under below
            $limit = (object)[ 'limit' => 0, 'count' => 0, 'sample_size' => 0 ];
        }

        $where = self::__getWhereFromFilter( $filter );
        $data  = self::__getData( $chunk, $filter );

        switch ( $filter->sampleType() ) {
            case 'segment_length_high_to_low':
                $sql = self::getSqlForSegmentLength( $limit, $where, 'high_to_low', $source_page );
                break;

            case 'segment_length_low_to_high':
                $sql = self::getSqlForSegmentLength( $limit, $where, 'low_to_high', $source_page );
                break;

            case 'edit_distance_high_to_low':
                $sql = self::getSqlForEditDistance( $limit, $where, 'high_to_low', $source_page );
                break;

            case 'edit_distance_low_to_high':
                $sql = self::getSqlForEditDistance( $limit, $where, 'low_to_high', $source_page );
                break;

            case 'regular_intervals':
                $sql = self::getSqlForRegularIntervals( $limit, $where, $source_page );
                break;

            case 'unlocked':
                $sql = self::getSqlForUnlocked( $where, $source_page );
                break;

            case 'ice':
                $sql = self::getSqlForIce( $where, $source_page );
                break;

            case 'modified_ice':
                $sql = self::getSqlForModifiedIce( $where, $source_page );
                break;

            case 'repetitions':
                $sql = self::getSqlForRepetition( $where, $source_page );
                break;

            case 'matches':
                $sql = self::getSqlForMatches( $where, $source_page );
                break;

            case 'mt':
            case 'fuzzies_50_74':
            case 'fuzzies_75_84':
            case 'fuzzies_85_94':
            case 'fuzzies_95_99':
                $sql = self::getSqlForMatchType( $where, $source_page );
                break;

            case 'todo':
                $sql = self::getSqlForTodo( $where, $data, $source_page);
                break;

            default:
                throw new \Exception( 'Sample type is not valid: ' . $filter->sampleType() );
                break;
        }

        $thisDao = new self();
        $stmt    = $thisDao->_getStatementForCache( $sql );

        return $thisDao->_fetchObject( $stmt, new ShapelessConcreteStruct, $data );
    }

    /**
     * @param $limit
     * @param $where
     *
     * @return string
     */
    public static function getSqlForRegularIntervals( $limit, $where, $source_page ) {

        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $ratio = round( $limit->count / $limit->limit );

        $sql = "SELECT id FROM (
            SELECT st.id_segment AS id,
            @curRow := @curRow + 1 AS row_number

          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment
           JOIN (SELECT @curRow := -1) r --  using -1 here makes the sample start from the first segment

           $ste_join

           WHERE 1

           $where->sql
           ORDER BY st.id_segment ASC
           ) sub WHERE row_number % $ratio = 0 ";

        return $sql;
    }

    public static function getSqlForEditDistance( $limit, $where, $sort, $source_page ) {
        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;
        $sqlSort = '';

        if ( $sort === 'high_to_low' ) {
            $sqlSort = 'DESC';
        } else {
            if ( $sort === 'low_to_high' ) {
                $sqlSort = 'ASC';
            }
        }

        $sql = "
          SELECT id FROM (
              SELECT st.id_segment AS id
              FROM
               segment_translations st JOIN jobs
               ON jobs.id = st.id_job
               AND jobs.password = :password
               AND jobs.id = :id_job
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               JOIN segments s ON s.id = st.id_segment

               $ste_join

           WHERE 1

               $where->sql
               ORDER BY st.edit_distance $sqlSort
               LIMIT $limit->limit ) t1
           ORDER BY t1.id ";

        return $sql;
    }

    public static function getSqlForSegmentLength( $limit, $where, $sort, $source_page ) {

        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sqlSort = '';

        if ( $sort === 'high_to_low' ) {
            $sqlSort = 'DESC';
        } else {
            if ( $sort === 'low_to_high' ) {
                $sqlSort = 'ASC';
            }
        }

        $sql = "SELECT id FROM (
          SELECT st.id_segment AS id
          FROM
           segment_translations st
           JOIN jobs ON jobs.id = st.id_job
               AND jobs.password = :password
               AND jobs.id = :id_job
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment

           $ste_join

           WHERE 1
           $where->sql
           ORDER BY CHAR_LENGTH(s.segment) $sqlSort
           LIMIT $limit->limit
          ) t1 ORDER BY t1.id ";

        return $sql;
    }

    /**
     * @param $source_page
     *
     * @return string
     */
    public static function segmentTranslationEventsJoin( $source_page ) {
        if ( $source_page ) {
            return " LEFT JOIN (
            
                SELECT id_segment as ste_id_segment, source_page 
                FROM  segment_translation_events 
                JOIN ( 
                    SELECT max(id) as _m_id FROM segment_translation_events
                        WHERE id_job = :id_job
                        AND id_segment BETWEEN :job_first_segment AND :job_last_segment
                        GROUP BY id_segment 
                ) AS X ON _m_id = segment_translation_events.id
                ORDER BY id_segment

            ) ste ON ste.ste_id_segment = st.id_segment " ;
        }

        return '';
    }

    public static function getSqlForUnlocked( $where, $source_page ) {
        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql = "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND st.locked = 0

           $ste_join

           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";

        return $sql;
    }

    public static function getSqlForMatchType( $where, $source_page ) {
        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql = "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment

           AND st.match_type = :match_type

           $ste_join

           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";

        return $sql;
    }

    public static function getSqlForIce( $where, $source_page ) {
        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql = "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment

           AND st.match_type = 'ICE'
           AND locked = 1
           AND version_number = 0

           $ste_join

           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";

        return $sql;
    }

    public static function getSqlForModifiedIce( $where, $source_page ) {
        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql = "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment

           AND st.match_type = 'ICE'
           AND locked = 1
           AND version_number > 0

           $ste_join

           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";

        return $sql;
    }



    public static function getSqlForRepetition( $where, $source_page ) {

        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql = "
            SELECT id_segment AS id, segment_hash FROM segment_translations JOIN(
                SELECT 
                    GROUP_CONCAT( st.id_segment ) AS id,
                    st.segment_hash as hash
                FROM segment_translations st

                $ste_join

                JOIN jobs 
                        ON jobs.id = st.id_job 
                        AND jobs.id = :id_job
                        AND jobs.password = :password
                        AND st.id_segment BETWEEN :job_first_segment AND :job_last_segment

                WHERE 1

                        $where->sql

                GROUP BY segment_hash, CONCAT( id_job, '-', password )
                HAVING COUNT( segment_hash ) > 1
            ) AS REPETITIONS ON REPETITIONS.hash = segment_translations.segment_hash AND FIND_IN_SET( id_segment, REPETITIONS.id )
            GROUP BY id_segment
        ";

        return $sql;
    }

    public static function getSqlForMatches( $where, $source_page ) {

        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql = "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND (st.match_type = :match_type_100_public 
           OR st.match_type = :match_type_100 
           OR st.match_type = :match_type_ice)

           $ste_join

           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";

        return $sql;
    }

    public static function getSqlForToDo( $where , $data, $source_page ) {

        $ste_join = self::segmentTranslationEventsJoin( $source_page ) ;

        $sql_condition = "";
        $sql_sp = "";

        if(array_key_exists("status_translated", $data)) {
            $sql_condition = " OR st.status = :status_translated ";
        }

        if(array_key_exists("status_approved", $data)) {
            $sql_condition = " OR st.status = :status_approved ";
            $sql_sp = " AND ste.source_page < :source_page";
        }

        $sql = "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND (st.status = :status_new
           OR st.status = :status_draft ".$sql_condition.")

           ".$ste_join."
           
           WHERE 1
           ".$where->sql."
           ".$sql_sp."
           ORDER BY st.id_segment
        ";

        return $sql;
    }

    protected function _buildResult( $data ) {
    }

}