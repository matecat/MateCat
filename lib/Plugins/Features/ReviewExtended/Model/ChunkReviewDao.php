<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 24/09/2018
 * Time: 12:33
 */

namespace Features\ReviewExtended\Model;

use Chunks_ChunkStruct;
use Constants;
use Database;

class ChunkReviewDao extends \LQA\ChunkReviewDao {

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @param null               $source_page
     *
     * @return int
     */
    public static function getPenaltyPointsForChunk( Chunks_ChunkStruct $chunk, $source_page = null ) {
        if ( is_null( $source_page ) ) {
            $source_page = Constants::SOURCE_PAGE_REVISION ;
        }

        $sql = "SELECT SUM(penalty_points) FROM qa_entries e
                JOIN jobs j on j.id = e.id_job
                    AND e.id_segment >= j.job_first_segment
                    AND e.id_segment <= j.job_last_segment
                WHERE j.id = :id_job
                    AND j.password = :password
                    AND source_page = :source_page
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job' => $chunk->id,
                'password' => $chunk->password,
                'source_page' => $source_page
        ] );

        $count =  $stmt->fetch();

        $penalty_points = $count[0] == null ? 0 : $count[0];
        return $penalty_points ;
    }

}
