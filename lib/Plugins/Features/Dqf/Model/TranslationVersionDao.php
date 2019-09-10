<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/03/2018
 * Time: 12:13
 */

namespace Features\Dqf\Model;

use DataAccess_AbstractDao;
use Database;
use Log;
use PDO;

class TranslationVersionDao extends DataAccess_AbstractDao {

    const TABLE = 'segment_translation_versions';

    /**
     * This function returns a data structure that answers to the following question:
     *
     *  - How did the segments change since a given date?
     *
     * @param $file
     * @param $since
     * @param $min
     * @param $max
     *
     * @return ExtendedTranslationStruct[]
     */

    public function getExtendedTranslationByFile( $file, $since, $min, $max ) {

        Log::doJsonLog('getExtendedTranslationByFile', func_get_args() ) ;

        $sql = "SELECT

                s.id_file,
                st.id_job,
                s.id,

                st.autopropagated_from,
                st.time_to_edit,
                st.translation,
                st.version_number AS current_version,
                st.suggestion_match,
                st.suggestions_array,
                st.suggestion,
                st.suggestion_source,
                st.suggestion_position,
                st.version_number,
                st.match_type,
                st.locked,

                stv.creation_date,
                stv.translation AS versioned_translation,
                stv.time_to_edit AS versioned_time_to_edit,
                stv.version_number

                FROM segment_translations st
                  JOIN segments s ON s.id = st.id_segment
                  LEFT JOIN segment_translation_versions stv ON st.id_segment = stv.id_segment
                    AND stv.creation_date >= :since

              WHERE id_file = :id_file
              AND s.id >= :min AND s.id <= :max

                ORDER BY 
                  s.id,  -- <== this ensures segments are processed from first to last
                  stv.id -- <== this ensures the first joined version is the last, all more recent intermediate
                         --     versions are skipped during the following cycle. See below `while` loop below. 
                " ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute([
                'id_file' => $file->id,
                'since'   => $since,
                'min'     => $min,
                'max'     => $max
        ]) ;

        /** @var ExtendedTranslationStruct[] $result */
        $result = [] ;

        while( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {

            /**
             * First off, skip the segment if we processed this record once.
             * The query result is structured in a way that we only need the first record because it joins
             * the segment with the oldest version in the batch, which is the one we need to determine the
             * initial state of the segment in the given time interval.
             *
             */

            if ( isset( $result[ $row['id'] ] ) ) {
                continue ;
            }

            /**
             * Start populating the data array for ExtendedTranslationStruct.
             */

            $data = [
                    'id_job'             => $row['id_job'],
                    'id_segment'         => $row['id'],
                    'translation_after'  => $row['translation'],
                    'time'               => $row['time_to_edit'] - ( $row['versioned_time_to_edit'] || 0 )
            ];

            /**
             * Find `translation_before`. This is tricky because this should reflect that the translator
             * actually found in the edit area when editing the segment, which may or may not be the content
             * of `segment_translations.translation`.
             *
             */

            if ( $this->__isFirstTranslation( $row ) ) {
                if ( $this->__isPreTranslated( $row ) ) {
                    $data['translation_before'] = '' . $this->__getOriginalVersion( $row ) ;
                }
                else {
                    $data['translation_before'] = '' . $row['suggestion'];
                }
            }
            else {
                $data['translation_before'] = '' . $row['versioned_translation'];
            }

            $data = $this->__setSegmentOrigin( $data, $row );

            $result[ $row['id'] ] = new ExtendedTranslationStruct( $data ) ;
        }

        return $result ;
    }

    private function __setSegmentOrigin( $data, $row ) {
        if ( !is_null( $row[ 'autopropagated_from' ] ) ) {
            $data[ 'segment_origin'] = 'TM' ;
            $data[ 'suggestion_match' ] = '100' ;

            return $data ;
        }

        if ( empty($row['suggestions_array']) ) {
            $data[ 'segment_origin'] = 'HT' ;

            return $data ;
        }

        if ( ( strpos( $row['match_type'], '100%' ) === 0 ) || $row['match_type'] == 'ICE' ) {
            $data['segment_origin']   = 'TM';
            $data['suggestion_match'] = '100';
        }
        elseif ( strpos( $row['match_type'], '%' ) !== false ) {
            $data['segment_origin'] = 'TM' ;
            $data['suggestion_match'] = $row['suggestion_match'];
        }
        elseif ( $row['match_type'] == 'MT' ) {
            $data['segment_origin'] = 'MT' ;
        }

        if ( !is_null( $row['suggestion'] ) && $row['translation'] !== $row['suggestion'] ) {
            // find match for the applied suggestion
           $suggestions = json_decode( $row['suggestions_array'], true );
           $selected    = $suggestions[ $row[ 'suggestion_position' ] ] ;

           if ( $selected['created_by'] == 'MT!' ) {
               $data[ 'segment_origin' ] = 'MT' ;
               $data[ 'suggestion_match' ] = null ;
           }
           else {
               $data[ 'segment_origin' ] = 'TM' ;
               $data[ 'suggestion_match'] = str_replace('%', '', $selected[ 'match' ]);
           }
        }

        return $data ;
    }

    private function __getOriginalVersion( $row ) {
        return is_null( $row['versioned_translation'] ) ? $row['translation'] : $row['versioned_translation'] ;
    }

    private function __isPreTranslated( $row ) {
        return $row['match_type'] == 'ICE' && $row['locked'] == 0 ;
    }

    private function __isFirstTranslation( $row ) {
        return is_null( $row['current_version'] ) || $row['current_version'] == 0 ;
    }


}