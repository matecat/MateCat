<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 24/09/2018
 * Time: 12:33
 */

namespace Features\ReviewExtended\Model;

class ChunkReviewDao extends \LQA\ChunkReviewDao {

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return int
     */
    public static function getPenaltyPointsForChunk( \Chunks_ChunkStruct $chunk ) {

        $sql = "SELECT SUM(penalty_points) FROM qa_entries e
                JOIN jobs j on j.id = e.id_job
                AND e.id_segment >= j.job_first_segment
                AND e.id_segment <= j.job_last_segment
                WHERE j.id = :id_job AND j.password = :password
        ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( array( 'id_job' => $chunk->id, 'password' => $chunk->password ) );
        $count =  $stmt->fetch();

        $penalty_points = $count[0] == null ? 0 : $count[0];
        return $penalty_points ;
    }

}
