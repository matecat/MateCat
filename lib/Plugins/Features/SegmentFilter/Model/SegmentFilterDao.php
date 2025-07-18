<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:25 AM
 */

namespace Features\SegmentFilter\Model;

use Constants_TranslationStatus;
use DataAccess\AbstractDao;
use DataAccess\IDaoStruct;
use DataAccess\ShapelessConcreteStruct;
use Database;
use Exception;
use Jobs_JobStruct;
use Model\Analysis\Constants\InternalMatchesConstants;
use ReflectionException;

class SegmentFilterDao extends AbstractDao {

    /**
     * @param Jobs_JobStruct   $chunk
     * @param FilterDefinition $filter
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public static function findSegmentIdsBySimpleFilter( Jobs_JobStruct $chunk, FilterDefinition $filter ): array {

        $sql = "SELECT st.id_segment AS id
            FROM
            segment_translations st
            JOIN jobs ON jobs.id = st.id_job
               AND jobs.id = :id_job
               AND jobs.password = :password
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               AND st.status = :status";

        $data = [
                'id_job'            => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment'  => $chunk->job_last_segment,
                'password'          => $chunk->password,
                'status'            => $filter->getSegmentStatus()
        ];

        $thisDao = new self();
        $stmt    = $thisDao->_getStatementForQuery( $sql );

        return $thisDao->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, $data );

    }

    /**
     * @param FilterDefinition $filter
     *
     * @return object
     */
    private static function __getWhereFromFilter( FilterDefinition $filter ): object {
        $where      = '';
        $where_data = [];

        if ( $filter->isFiltered() ) {
            $where      = " AND st.status = :status ";
            $where_data = [ 'status' => $filter->getSegmentStatus() ];
        }

        return (object)[ 'sql' => $where, 'data' => $where_data ];
    }


    private static function __getData( Jobs_JobStruct $chunk, FilterDefinition $filter ): array {
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

        if ( $filter->sampleData() ) {
            switch ( $filter->sampleType() ) {
                case 'mt':
                    $data = array_merge( $data, [
                            'match_type' => InternalMatchesConstants::MT,
                    ] );
                    break;

                case 'matches':
                    $data = array_merge( $data, [
                            'match_type_100_public' => InternalMatchesConstants::TM_100_PUBLIC,
                            'match_type_100'        => InternalMatchesConstants::TM_100,
                    ] );
                    break;

                case 'fuzzies_50_74':
                    $data = array_merge( $data, [
                            'match_type' => InternalMatchesConstants::TM_50_74,
                    ] );
                    break;

                case 'fuzzies_75_84':
                    $data = array_merge( $data, [
                            'match_type' => InternalMatchesConstants::TM_75_84,
                    ] );
                    break;

                case 'fuzzies_85_94':
                    $data = array_merge( $data, [
                            'match_type' => InternalMatchesConstants::TM_85_94,
                    ] );
                    break;

                case 'fuzzies_95_99':
                    $data = array_merge( $data, [
                            'match_type' => InternalMatchesConstants::TM_95_99,
                    ] );
                    break;

                case 'todo':
                    $data = array_merge( $data, [
                            'status_new'   => Constants_TranslationStatus::STATUS_NEW,
                            'status_draft' => Constants_TranslationStatus::STATUS_DRAFT
                    ] );

                    if ( $chunk->getIsReview() ) {
                        $data = array_merge( $data, [
                                'status_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
                        ] );
                    }

                    if ( $chunk->isSecondPassReview() ) {
                        $data = array_merge( $data, [
                                'status_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
                                'status_approved'   => Constants_TranslationStatus::STATUS_APPROVED,
                        ] );
                    }

                    break;
            }

        }

        return $data;
    }

    /**
     * @param Jobs_JobStruct   $chunk
     * @param FilterDefinition $filter
     *
     * @return object
     */
    private static function __getLimit( Jobs_JobStruct $chunk, FilterDefinition $filter ): object {

        $where = self::__getWhereFromFilter( $filter );

        $countSql = "SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
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

        $limit = round( ( $count / 100 ) * $filter->sampleSize() );

        return (object)[
                'limit'       => $limit,
                'count'       => $count,
                'sample_size' => $filter->sampleSize()
        ];
    }

    /**
     * @param Jobs_JobStruct   $chunk
     * @param FilterDefinition $filter
     *
     * @return IDaoStruct[]
     * @throws Exception
     */
    public static function findSegmentIdsForSample( Jobs_JobStruct $chunk, FilterDefinition $filter ): array {

        if ( $filter->sampleSize() > 0 ) {
            $limit = self::__getLimit( $chunk, $filter );
        } else {
            //initialize limit with 0 in all attributes because we use $limit attributes in methods called under below
            $limit = (object)[ 'limit' => 0, 'count' => 0, 'sample_size' => 0 ];
        }

        $where = self::__getWhereFromFilter( $filter );
        $data  = self::__getData( $chunk, $filter );

        switch ( $filter->sampleType() ) {
            case 'segment_length_high_to_low':
                $sql = self::getSqlForSegmentLength( $limit, $where, 'high_to_low' );
                break;

            case 'segment_length_low_to_high':
                $sql = self::getSqlForSegmentLength( $limit, $where, 'low_to_high' );
                break;

            case 'edit_distance_high_to_low':
                $sql = self::getSqlForEditDistance( $limit, $where, 'high_to_low' );
                break;

            case 'edit_distance_low_to_high':
                $sql = self::getSqlForEditDistance( $limit, $where, 'low_to_high' );
                break;

            case 'regular_intervals':
                $sql = self::getSqlForRegularIntervals( $limit, $where );
                break;

            case 'unlocked':
                $sql = self::getSqlForUnlocked( $where );
                break;

            case 'ice':
                $sql = self::getSqlForIce( $where );
                break;

            case 'modified_ice':
                $sql = self::getSqlForModifiedIce( $where );
                break;

            case 'repetitions':
                $sql = self::getSqlForRepetition( $where );
                break;

            case 'matches':
                $sql = self::getSqlForMatches( $where );
                break;

            case 'mt':
            case 'fuzzies_50_74':
            case 'fuzzies_75_84':
            case 'fuzzies_85_94':
            case 'fuzzies_95_99':
                $sql = self::getSqlForMatchType( $where );
                break;

            case 'todo':
                $sql = self::getSqlForTodo( $where, $chunk->getIsReview(), $chunk->isSecondPassReview() );
                break;

            default:
                throw new Exception( 'Sample type is not valid: ' . $filter->sampleType() );
        }

        $thisDao = new self();
        $stmt    = $thisDao->_getStatementForQuery( $sql );

        return $thisDao->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, $data );
    }

    /**
     * @param object $limit
     * @param object $where
     *
     * @return string
     */
    public static function getSqlForRegularIntervals( object $limit, object $where ): string {

        $ratio = round( $limit->count / $limit->limit );

        return "SELECT id FROM (
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
           WHERE 1
           $where->sql
           ORDER BY st.id_segment ASC
           ) sub WHERE `row_number` % $ratio = 0 ";
    }

    public static function getSqlForEditDistance( object $limit, object $where, string $sort ): string {

        $sqlSort = '';

        if ( $sort === 'high_to_low' ) {
            $sqlSort = 'DESC';
        } else {
            if ( $sort === 'low_to_high' ) {
                $sqlSort = 'ASC';
            }
        }

        return "SELECT st.id_segment AS id
              FROM
               segment_translations st JOIN jobs
               ON jobs.id = st.id_job
               AND jobs.password = :password
               AND jobs.id = :id_job
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               JOIN segments s ON s.id = st.id_segment
            WHERE 1
               $where->sql
               ORDER BY st.edit_distance $sqlSort
               LIMIT $limit->limit ;";
    }

    public static function getSqlForSegmentLength( object $limit, object $where, string $sort ): string {

        $sqlSort = '';

        if ( $sort === 'high_to_low' ) {
            $sqlSort = 'DESC';
        } else {
            if ( $sort === 'low_to_high' ) {
                $sqlSort = 'ASC';
            }
        }

        return "SELECT st.id_segment AS id
          FROM
           segment_translations st
           JOIN jobs ON jobs.id = st.id_job
               AND jobs.password = :password
               AND jobs.id = :id_job
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment
           WHERE 1
           $where->sql
           ORDER BY CHAR_LENGTH(s.segment) $sqlSort
           LIMIT $limit->limit";
    }

    public static function getSqlForUnlocked( object $where ): string {
        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND st.locked = 0
           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";
    }

    public static function getSqlForMatchType( object $where ): string {

        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND st.match_type = :match_type
           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";
    }

    public static function getSqlForIce( object $where ): string {

        return "
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
           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";
    }

    public static function getSqlForModifiedIce( object $where ): string {

        return "
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
           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";
    }


    public static function getSqlForRepetition( object $where ): string {

        return "
            SELECT id_segment AS id, segment_hash FROM segment_translations JOIN(
                SELECT 
                    GROUP_CONCAT( st.id_segment ) AS id,
                    st.segment_hash as hash
                FROM segment_translations st
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
    }

    public static function getSqlForMatches( object $where ): string {

        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND (st.match_type = :match_type_100_public 
           OR st.match_type = :match_type_100)
           WHERE 1
           $where->sql
           ORDER BY st.id_segment
        ";
    }

    public static function getSqlForToDo( object $where, bool $isReview = false, bool $isSecondPassReview = false ): string {

        $sql_condition = "";
        $sql_sp        = "";

        if ( $isReview ) {
            $sql_condition = " OR st.status = :status_translated ";
        }

        if ( $isSecondPassReview ) {
            $sql_condition = " OR st.status = :status_translated OR st.status = :status_approved ";
        }

        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND (st.status = :status_new
           OR st.status = :status_draft " . $sql_condition . ")
           WHERE 1
           " . $where->sql . "
           " . $sql_sp . "
           ORDER BY st.id_segment
        ";
    }

    protected function _buildResult( array $array_result ) {
    }

}