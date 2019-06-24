<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 20/06/19
 * Time: 15.07
 *
 */

namespace TMSService;


use Constants_TranslationStatus;
use Database;
use Log;
use PDO;
use PDOException;

class TMSServiceDao {

    /**
     * @param $jid
     * @param $jPassword
     *
     * @return array
     */
    public static function getTranslationsForTMXExport( $jid, $jPassword ) {

        $db = Database::obtain();

        $sql = "
        SELECT
            id_segment,
            segment_translations.id_job,
            filename,
            segment,
            translation,
            translation_date,
            segment_translations.status
        FROM segment_translations
        JOIN segments ON id = id_segment
        JOIN files ON segments.id_file = files.id
        JOIN jobs ON jobs.id = segment_translations.id_job AND password = :password
            WHERE segment_translations.id_job = :id_job
            AND show_in_cattool = 1
";

        try {
            $stmt = $db->getConnection()->prepare( $sql );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job'   => $jid,
                    'password' => $jPassword
            ] );
            $results = $stmt->fetchAll();
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        return $results;

    }

    public static function getMTForTMXExport( $jid, $jPassword ) {

        $db = Database::obtain();

        $sql = "
        SELECT 
             id_segment, 
             st.id_job, 
             '' as filename, 
             segment, 
             suggestion as translation,
             IF( st.status IN ( :_translated, :_approved ), translation_date, j.create_date ) as translation_date
        FROM segment_translations st
        JOIN segments ON id = id_segment
        JOIN jobs j ON j.id = st.id_job AND password = :password
            WHERE st.id_job = :id_job
            AND show_in_cattool = 1
            AND suggestion_source in ('MT','MT-')
";

        try {
            $stmt = $db->getConnection()->prepare( $sql );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job'      => $jid,
                    'password'    => $jPassword,
                    '_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
                    '_approved'   => Constants_TranslationStatus::STATUS_APPROVED
            ] );
            $results = $stmt->fetchAll();
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        return $results;
    }

    public static function getTMForTMXExport( $jid, $jPassword ) {

        $db = Database::obtain();

        $sql = "
        SELECT 
            id_segment, 
            st.id_job, '' as filename, 
            segment, 
            suggestion as translation,
            IF( st.status IN ( :_translated, :_approved ), translation_date, jobs.create_date ) as translation_date,
            st.status, 
            suggestions_array, 
            jobs.tm_keys, 
            id_customer
        FROM segment_translations st
        JOIN segments ON id = id_segment
        JOIN jobs ON jobs.id = st.id_job AND password = :password
        JOIN projects ON jobs.id_project = projects.id
            WHERE st.id_job = :id_job
            AND show_in_cattool = 1
            AND suggestion_source is not null
            AND ( suggestion_source = 'TM' OR suggestion_source not in ( 'MT', 'MT-' ) )
";

        try {
            $stmt = $db->getConnection()->prepare( $sql );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute( [
                    'id_job'      => $jid,
                    'password'    => $jPassword,
                    '_translated' => Constants_TranslationStatus::STATUS_TRANSLATED,
                    '_approved'   => Constants_TranslationStatus::STATUS_APPROVED
            ] );
            $results = $stmt->fetchAll();
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        foreach ( $results as $key => $value ) {

            //we already extracted a 100% match by definition
            if ( in_array( $value[ 'status' ], [
                            Constants_TranslationStatus::STATUS_TRANSLATED,
                            Constants_TranslationStatus::STATUS_APPROVED
                    ]
            )
            ) {
                continue;
            }

            $suggestions_array = json_decode( $value[ 'suggestions_array' ] );
            foreach ( $suggestions_array as $_k => $_sugg ) {

                //we want the highest value of TM and we must exclude the MT
                if ( strpos( $_sugg->created_by, 'MT' ) !== false ) {
                    continue;
                }

                //override the content of the result with the fuzzy matches
                $results[ $key ][ 'segment' ]     = $_sugg->segment;
                $results[ $key ][ 'translation' ] = $_sugg->translation;
                $results[ $key ][ '_created_by' ] = 'MateCat_OmegaT_Export';

                //stop, we found the first TM value in the list
                break;

            }

            //if no TM found unset the result
            if ( !isset( $results[ $key ][ '_created_by' ] ) ) {
                unset( $results[ $key ] );
            }

        }

        return $results;
    }


}