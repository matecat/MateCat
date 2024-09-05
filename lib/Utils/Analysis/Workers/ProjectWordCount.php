<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 19/06/19
 * Time: 19.25
 *
 */

namespace Analysis\Workers;

use Database;
use Log;
use PDO;
use PDOException;
use RuntimeException;

trait ProjectWordCount {

    /**
     * This function is heavy, use but only if it is necessary
     *
     * (Used in TMAnalysisWorker and FastAnalysis)
     *
     * @param $pid
     *
     * @return array
     */
    protected function getProjectSegmentsTranslationSummary( $pid ): array {

        //TOTAL and eq_word should be equals, BUT
        //tm Analysis can fail on some rows because of external service nature, so use TOTAL field instead of eq_word
        //to set the global word counter in job
        //Ref: jobs.new_words
        $query = "
                SELECT
                    id_job,
                    password,
                    SUM(eq_word_count) AS eq_wc,
                    SUM(standard_word_count) AS st_wc,
                    SUM( IF( COALESCE( eq_word_count, 0 ) = 0, raw_word_count, eq_word_count) ) as TOTAL,
                    COUNT( s.id ) AS project_segments,
                    0 AS num_analyzed
                FROM segment_translations st
                     JOIN segments s ON s.id = id_segment
                     INNER JOIN jobs j ON j.id=st.id_job
                WHERE j.id_project = :pid
                AND s.show_in_cattool = 1
                GROUP BY id_job WITH ROLLUP
        ";

        try {

            $db = Database::obtain();
            //Needed to address the query to the master database if exists
            $stmt = $db->getConnection()->prepare( $query );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [ 'pid' => $pid ] );
            $results = $stmt->fetchAll();
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            throw new RuntimeException( $e );
        }

        return $results;
    }


}