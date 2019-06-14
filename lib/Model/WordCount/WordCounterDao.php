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
                'id' => $wStruct->getIdJob(),
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

}