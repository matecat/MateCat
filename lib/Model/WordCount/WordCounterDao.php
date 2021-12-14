<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 14/06/19
 * Time: 9.45
 *
 */

namespace WordCount;


use DataAccess_AbstractDao;
use Database;
use Log;
use PDOException;
use WordCount_Struct;

class WordCounterDao extends DataAccess_AbstractDao {

    /**
     * Update the word count for the job
     *
     * We perform an update in join with jobs table
     * because we want to update the word count only for the current chunk
     *
     * Update the status of segment_translation is needed to avoid duplicated calls
     * ( The second call fails for status condition )
     *
     * @param WordCount_Struct $wStruct
     *
     * @return int
     */
    public static function updateWordCount( WordCount_Struct $wStruct ) {

        $db = Database::obtain();

        //Update in Transaction
        $query = "UPDATE jobs AS j SET
                        new_words = new_words + :newWords,
                        draft_words = draft_words + :draftWords,
                        translated_words = translated_words + :translatedWords,
                        approved_words = approved_words + :approvedWords,
                        rejected_words = rejected_words + :rejectedWords
                  WHERE j.id = :id_job
                  AND j.password = :password";

        $bind_keys = [
                'newWords'        => $wStruct->getNewWords(),
                'draftWords'      => $wStruct->getDraftWords(),
                'translatedWords' => $wStruct->getTranslatedWords(),
                'approvedWords'   => $wStruct->getApprovedWords(),
                'rejectedWords'   => $wStruct->getRejectedWords(),
                'id_job'          => $wStruct->getIdJob(),
                'password'        => $wStruct->getJobPassword()
        ];

        try {
            $stmt = $db->getConnection()->prepare( $query );
            $stmt->execute( $bind_keys );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        return $stmt->rowCount();

    }

    public static function initializeWordCount( WordCount_Struct $wStruct ) {

        $db = Database::obtain();

        $data                       = [];
        $data[ 'new_words' ]        = $wStruct->getNewWords();
        $data[ 'draft_words' ]      = $wStruct->getDraftWords();
        $data[ 'translated_words' ] = $wStruct->getTranslatedWords();
        $data[ 'approved_words' ]   = $wStruct->getApprovedWords();
        $data[ 'rejected_words' ]   = $wStruct->getRejectedWords();

        $where = [
                'id'       => $wStruct->getIdJob(),
                'password' => $wStruct->getJobPassword()
        ];

        try {
            $db->update( 'jobs', $data, $where );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        return $db->affected_rows;

    }

    /**
     * Inefficient function for high traffic requests like setTranslation
     *
     * Leave untouched for getSegmentsController, split job recalculation
     * because of file level granularity in payable words
     *
     * @param      $id_job
     * @param null $id_file
     * @param null $jPassword
     *
     * @return array
     *
     */
    public static function getStatsForJob( $id_job, $id_file = null, $jPassword = null ) {

        /*
         * -- TOTAL field is not used, but we keep here to easy check the values and for documentation
         *
         * In the segment_translations table we always have the correct value for eq_word_count, such value is taken by multiplying the raw word count value by the payable rate discount ( / 100 )
         *
         * In the case of pre-translation, the rows in this table are marked as ICEs, set as locked = 0,  and the equivalent word count is set as previous described.
         *
         * But the pre-translations must be shown in the UI as part of the TOTAL ( this does not apply to the true ICEs because they will set everytime as equivalent even if their values is Zero ).
         *
         * To disambiguate the case on which pre-translated rows has Zero payable rate, we need 3 conditions:
         * - match_type is ICE
         * - suggestion_match IS NULL ( since those segments are not sent to the TM analysis )
         * - equivalent_word_count = 0 ( case to disambiguate ) and raw_word_count != 0 ( this means that the payable rate is Zero )
         *
         */
        $query = "
            SELECT
                    j.id,
                    SUM(
                            IF( st.match_type = 'ICE' AND st.suggestion_match IS NULL AND st.eq_word_count = 0 and s.raw_word_count != 0, s.raw_word_count, st.eq_word_count )
                       ) as TOTAL,
                    SUM(
                            IF(
                                st.status IS NULL OR
                                st.status = 'NEW',
                                IF( st.match_type = 'ICE' AND st.suggestion_match IS NULL AND st.eq_word_count = 0 and s.raw_word_count != 0, s.raw_word_count, st.eq_word_count ),0 )
                       ) as NEW,
                    SUM(
                            IF( 
                                st.status IS NULL OR st.status = 'DRAFT' OR st.status = 'NEW',
                                IF( st.match_type = 'ICE' AND st.suggestion_match IS NULL AND st.eq_word_count = 0 and s.raw_word_count != 0, s.raw_word_count, st.eq_word_count ),0 )
                       ) as DRAFT,
                    SUM(
                            IF( st.status='TRANSLATED', IF( st.match_type = 'ICE' AND st.suggestion_match IS NULL AND st.eq_word_count = 0 and s.raw_word_count != 0, s.raw_word_count, st.eq_word_count ),0 )
                       ) as TRANSLATED,
                       
                    SUM(
                            IF(st.status='APPROVED', IF( st.match_type = 'ICE' AND st.suggestion_match IS NULL AND st.eq_word_count = 0 and s.raw_word_count != 0, s.raw_word_count, st.eq_word_count ),0 )
                       ) as APPROVED,
                    SUM(
                            IF(st.status='REJECTED', IF( st.match_type = 'ICE' AND st.suggestion_match IS NULL AND st.eq_word_count = 0 and s.raw_word_count != 0, s.raw_word_count, st.eq_word_count ),0 )
                       ) as REJECTED
                FROM jobs AS j
                INNER JOIN files_job as fj on j.id = fj.id_job
                INNER join segments as s on fj.id_file = s.id_file
                LEFT join segment_translations as st on s.id = st.id_segment and st.id_job = j.id
                WHERE j.id = :id_job
 			    AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
			";

        $db = Database::obtain();

        $bind_values = [ 'id_job' => $id_job ];

        if ( !empty( $jPassword ) ) {
            $bind_values[ 'password' ] = $jPassword;
            $query                     .= " and j.password = :password";
        }

        if ( !empty( $id_file ) ) {
            $bind_values[ 'id_file' ] = $id_file;
            $query                    .= " and fj.id_file = :id_file";
        }

        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( \PDO::FETCH_ASSOC );
        $stmt->execute( $bind_values );
        $results = $stmt->fetchAll();
        $stmt->closeCursor();

        return $results;
    }


}