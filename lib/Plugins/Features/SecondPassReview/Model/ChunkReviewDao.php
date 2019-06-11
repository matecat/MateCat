<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/06/2019
 * Time: 19:11
 */

namespace Features\SecondPassReview\Model;


use Chunks_ChunkStruct;

class ChunkReviewDao extends \Features\ReviewExtended\Model\ChunkReviewDao {

    public function recountAdvancementWords( Chunks_ChunkStruct $chunk, $source_page ) {
        $sql = "
            SELECT sum(eq_word_count) FROM segments s
        JOIN segment_translations st on st.id_segment = s.id
        JOIN jobs j on j.id = st.id_job
        AND s.id <= j.job_last_segment
        AND s.id >= j.job_first_segment
        JOIN (
        SELECT id_segment as id_segment, source_page FROM segment_translation_events
        WHERE id IN (
        SELECT max(id) FROM segment_translation_events
            WHERE id_job = :id_job
            AND id_segment BETWEEN :job_first_segment AND :job_last_segment 
            GROUP BY id_segment
        )
        HAVING source_page = :source_page
        ORDER BY id_segment
        ) ste ON ste.id_segment = s.id

        WHERE
        j.id = :id_job AND j.password = :password  "  ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute([
                'id_job' => $chunk->id,
                'password' => $chunk->password,
                'source_page' => $source_page,
                'job_first_segment' => $chunk->job_first_segment,
                'job_last_segment' => $chunk->job_last_segment
        ]);

        $result = $stmt->fetch();
        return $result[0] == null ? 0 : $result[0];


    }


}