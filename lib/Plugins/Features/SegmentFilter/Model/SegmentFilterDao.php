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

    public static function findSegmentIdsForSample( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {

        /**
         * first thing to do here is to find the number of records to return.
         * based on the sample size we do a count
         */
        // first thing to do here is to find the number of records to return

        $where = '';
        $where_data = array();

        if ( $filter->isFiltered() ) {
            $where = " AND st.status = :status ";
            $where_data = array('status' => $filter->getSegmentStatus() );
        }

        $countSql = "SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           $where ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $countSql );

        $data = array(
                'id_job' => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment' => $chunk->job_last_segment,
                'password' => $chunk->password
        );

        if (!empty($where_data) ) {
            $data = array_merge($data, $where_data);
        }

        $stmt->execute($data);
        $count = $stmt->rowCount();

        if ( $count == 0 ) {
            // TODO: handle case
        }

        $limit = ceil(( $count / 100 ) * $filter->sampleSize());

        $sql = '';

        if ( $filter->sampleType() == 'segment_length') {
            $sql = self::getSqlForSegmentLength( $limit, $where );
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($data);
        return $stmt->fetchAll();
    }

    public static function getSqlForSegmentLength( $limit, $where ) {
        $sql = "SELECT id FROM (
            SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment
           $where
           ORDER BY CHAR_LENGTH(s.segment) DESC
           LIMIT $limit ) sub ORDER BY 1 ";

        return $sql ;
    }

    protected function _buildResult( $data ) { }

}