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
        SELECT s.id FROM segments s
           LEFT JOIN segment_translations st ON s.id = st.id_segment
           WHERE id_job = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND s.show_in_cattool = 1
           AND st.status = :status
           ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $data = array(
                'id_job' => $chunk->id,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment' => $chunk->job_last_segment,
                'status' => $filter->getSegmentStatus()
        );

        $stmt->execute($data);

        return $stmt->fetchAll();
    }

    protected function _buildResult( $data ) { }

}