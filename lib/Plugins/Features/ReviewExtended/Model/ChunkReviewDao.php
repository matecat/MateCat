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
use LQA\ChunkReviewStruct;

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
            $source_page = Constants::SOURCE_PAGE_REVISION;
        }

        $sql = "SELECT SUM(penalty_points) FROM qa_entries e
                JOIN jobs j on j.id = e.id_job
                    AND e.id_segment >= j.job_first_segment
                    AND e.id_segment <= j.job_last_segment
                WHERE j.id = :id_job
                    AND j.password = :password
                    AND source_page = :source_page
                    AND e.deleted_at IS NULL
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'source_page' => $source_page
        ] );

        $count = $stmt->fetch();

        $penalty_points = $count[ 0 ] == null ? 0 : $count[ 0 ];

        return $penalty_points;
    }

    public function getPenaltyPointsForChunkAndSourcePageAndSegment( $chunk, $segment_ids, $source_page ) {
        $segment_ids = implode( ',', $segment_ids );

        $sql = "SELECT SUM(penalty_points) FROM qa_entries e
                JOIN jobs j on j.id = e.id_job
                    AND e.id_segment >= j.job_first_segment
                    AND e.id_segment <= j.job_last_segment
                WHERE j.id = :id_job
                    AND j.password = :password
                    AND e.id_segment IN ( $segment_ids ) 
                    AND source_page = :source_page 
                    AND e.deleted_at IS NULL
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'source_page' => $source_page
        ] );

        $count = $stmt->fetch();

        $penalty_points = $count[ 0 ] == null ? 0 : $count[ 0 ];

        return $penalty_points;
    }

    public function countTimeToEdit( Chunks_ChunkStruct $chunk, $source_page ) {
        $sql = "
            SELECT SUM( time_to_edit ) FROM jobs
                JOIN segment_translation_events ste
                  ON jobs.id = ste.id_job
                  AND ste.id_segment >= jobs.job_first_segment AND ste.id_segment <= jobs.job_last_segment

                WHERE jobs.id = :id_job AND jobs.password = :password
                  AND ste.source_page = :source_page

                  GROUP BY ste.source_page

        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'source_page' => $source_page,
        ] );

        $result = $stmt->fetch();

        return $result[ 0 ] == null ? 0 : $result[ 0 ];
    }

    public function recountAdvancementWords( Chunks_ChunkStruct $chunk, $source_page ) {

        $sql = "
            SELECT SUM( eq_word_count ) FROM segment_translations st
                JOIN jobs j on j.id = st.id_job
                AND st.id_segment <= j.job_last_segment
                AND st.id_segment >= j.job_first_segment
            LEFT JOIN (
            
                SELECT id_segment as ste_id_segment, source_page 
                FROM  segment_translation_events 
                JOIN ( 
                    SELECT max(id) as _m_id FROM segment_translation_events
                        WHERE id_job = :id_job
                        AND id_segment BETWEEN :job_first_segment AND :job_last_segment
                        GROUP BY id_segment 
                ) AS X ON _m_id = segment_translation_events.id
                ORDER BY id_segment
                
            ) ste ON ste.ste_id_segment = st.id_segment

            WHERE
                j.id = :id_job AND j.password = :password
                AND
                ( source_page = :source_page OR
                  ( :source_page = 2 AND ste.ste_id_segment IS NULL and match_type = 'ICE' AND locked = 1 and st.status = 'APPROVED' )
                  ) ;
            ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'            => $chunk->id,
                'password'          => $chunk->password,
                'source_page'       => $source_page,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment'  => $chunk->job_last_segment
        ] );

        $result = $stmt->fetch();

        return $result[ 0 ] == null ? 0 : $result[ 0 ];
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     * @param array             $data
     *
     * @return bool
     * @throws \Exception
     */
    public function passFailCountsAtomicUpdate( ChunkReviewStruct $chunkReview, $data = [] ) {

        $chunkReview->is_pass              = $data[ 'is_pass' ];
        $chunkReview->penalty_points       = $chunkReview->penalty_points + $data[ 'penalty_points' ];
        $chunkReview->reviewed_words_count = $chunkReview->reviewed_words_count + $data[ 'reviewed_words_count' ];
        $chunkReview->advancement_wc       = $chunkReview->advancement_wc + $data[ 'advancement_wc' ];
        $chunkReview->total_tte            = $chunkReview->total_tte + $data[ 'total_tte' ];

        return ChunkReviewDao::updateStruct( $chunkReview, [ 'fields' => [ 'is_pass', 'penalty_points', 'reviewed_words_count', 'advancement_wc', 'total_tte' ] ] );
    }
}
