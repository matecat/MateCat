<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:25 AM
 */

namespace Features\SegmentFilter\Model;

use Features\SegmentFilter\Model\FilterDefinition;
use Chunks_ChunkStruct ;


class SegmentFilterDao extends \DataAccess_AbstractDao {

    /**
     * @param \Chunks_ChunkStruct $chunk
     * @param FilterDefinition    $filter
     *
     * @return array
     */
    public static function findSegmentIdsBySimpleFilter( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {

       $sql = "
        SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND st.status = :status
           ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $data = array(
                'id_job' => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment' => $chunk->job_last_segment,
                'password' => $chunk->password,
                'status' => $filter->getSegmentStatus()
        );

        $stmt->execute($data);

        return $stmt->fetchAll();
    }

    /**
     * @param $filter
     *
     * @return object
     */
    private static function __getWhereFromFilter( FilterDefinition $filter ) {
        $where = '';
        $where_data = array();

        if ( $filter->isFiltered() ) {
            $where = " AND st.status = :status ";
            $where_data = array('status' => $filter->getSegmentStatus() );
        }
        return (object) array( 'sql' => $where, 'data' => $where_data );
    }


    private static function __getData( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {
        $data = array(
                'id_job' => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment' => $chunk->job_last_segment,
                'password' => $chunk->password
        );

        if ( $filter->getSegmentStatus()) {
            $data = array_merge( $data, array(
                'status' => $filter->getSegmentStatus()
            ));
        }

        return $data;
    }
    /**
     * @param $chunk
     * @param $filter
     *
     * @return object
     */
    private static function __getLimit( Chunks_ChunkStruct $chunk, FilterDefinition $filter) {

        $where = self::__getWhereFromFilter( $filter );

        $countSql = "SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           $where->sql ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $countSql );

        $data = self::__getData( $chunk, $filter );

        if (!empty($where->data ) ) {
            $data = array_merge($data, $where->data );
        }

        $stmt->execute($data);
        $count = $stmt->rowCount();

        if ( $count == 0 ) {
            // TODO: handle case
        }

        $limit = round(( $count / 100 ) * $filter->sampleSize());
        return (object) array(
                'limit' => $limit,
                'count' => $count,
                'sample_size' => $filter->sampleSize()
        );
    }

    public static function findSegmentIdsForSample( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {

        $limit = self::__getLimit($chunk, $filter);
        $where = self::__getWhereFromFilter( $filter );

        $sql = '';

        switch ( $filter->sampleType() ) {
            case 'segment_length_high_to_low':
                $data = self::__getData( $chunk, $filter );
                $sql = self::getSqlForSegmentLength( $limit, $where, 'high_to_low' );
                break;
            case 'segment_length_low_to_high':
                $data = self::__getData( $chunk, $filter );
                $sql = self::getSqlForSegmentLength( $limit, $where, 'low_to_high' );
                break;
            case 'edit_distance_high_to_low':
                $data = self::__getData( $chunk, $filter );
                $sql = self::getSqlForEditDistance( $limit, $where, 'high_to_low' );
                break;
            case 'edit_distance_low_to_high':
                $data = self::__getData( $chunk, $filter );
                $sql = self::getSqlForEditDistance( $limit, $where, 'low_to_high' );
                break;
            case 'regular_intervals':
                $data = self::__getData( $chunk, $filter );
                $sql = self::getSqlForRegularIntervals( $limit, $where );
                break;
            default:
                throw new \Exception('Sample type is not valid: '. $filter->sampleType());
                break;
        }

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($data);
        return $stmt->fetchAll();
    }

    /**
     * @param $limit
     * @param $where
     *
     * @return string
     */
    public static function getSqlForRegularIntervals( $limit, $where ) {

        $ratio = round($limit->count / $limit->limit ) ;

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
           WHERE 1
           $where->sql
           ORDER BY st.id_segment ASC
           ) sub WHERE row_number % $ratio = 0 ";

        return $sql ;
    }

    public static function getSqlForEditDistance( $limit, $where, $sort ) {
        $sqlSort = '';

        if( $sort === 'high_to_low' ) {
            $sqlSort = 'DESC';
        } else if( $sort === 'low_to_high' ) {
            $sqlSort = 'ASC';
        }

        $sql = "SELECT st.id_segment AS id
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
           LIMIT $limit->limit ";

        return $sql ;
    }

    public static function getSqlForSegmentLength( $limit, $where, $sort ) {
        $sqlSort = '';

        if( $sort === 'high_to_low' ) {
            $sqlSort = 'DESC';
        } else if( $sort === 'low_to_high' ) {
            $sqlSort = 'ASC';
        }

        $sql = "SELECT st.id_segment AS id
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
           ORDER BY CHAR_LENGTH(s.segment) $sqlSort
           LIMIT $limit->limit ";

        return $sql ;
    }

    protected function _buildResult( $data ) { }

}