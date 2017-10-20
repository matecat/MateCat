<?php

use Features\Dqf\Model\ExtendedTranslationStruct;

class Translations_TranslationVersionDao extends DataAccess_AbstractDao {
    const TABLE = 'segment_translation_versions';

    public $source_page ;
    public $uid ;

    protected function _buildResult( $array_result ) {
    }

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

        Log::doLog('getExtendedTranslationByFile', func_get_args() ) ;

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

                ORDER BY s.id, stv.id
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
            if ( isset( $result[ $row['id'] ] ) ) {
                // Due to the ORDER instruction of the query, this skips all versions but the last.
                continue ;
            }

            $data = [
                    'id_job'             => $row['id_job'],
                    'id_segment'         => $row['id'],
                    'translation_after'  => $row['translation'],
                    'time'               => $row['time_to_edit'] - ( $row['versioned_time_to_edit'] || 0 )
            ];

            if ( $this->isFirstBatch( $row ) ) {
                if ( $this->isPreTranslated( $row ) ) {
                    $data['translation_before'] = $this->getOriginalVersion( $row ) ;
                }
                else {
                    $data['translation_before'] = $row['suggestion'];
                }
            }
            else { // Not first batch, no need to consider suggestion
                $data['translation_before'] = $row['versioned_translation'];
            }

            $data['translation_before'] = is_null( $row['translation_before'] ) ? '' : $row['translation_before'] ;

            // TODO: ExtendedTranslationStruct is under DQF namespace, while this DAO should not be aware of DQF
            $result[ $row['id'] ] = new ExtendedTranslationStruct( $data ) ;
        }

        return $result ;
    }

    private function getOriginalVersion( $row ) {
        return is_null( $row['versioned_translation'] ) ? $row['translation'] : $row['versioned_translation'] ;
    }

    private function isPreTranslated( $row ) {
        return $row['match_type'] == 'ICE' && $row['locked'] == 0 ;
    }

    private function isFirstBatch( $row ) {
        return is_null( $row['current_version'] ) || $row['current_version'] == 0 ;
    }

    /**
     * @param $id_job
     *
     * @return array
     */
    public static function getVersionsForJob($id_job) {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job " .
            " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql );

        $stmt->execute(
            array( 'id_job' => $id_job )
        );

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            'Translations_TranslationVersionStruct'
        );

        return $stmt->fetchAll();
    }

    public static function getVersionsForChunk( Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT * FROM segment_translation_versions " .
                " WHERE id_job = :id_job " .
                " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute(
                array( 'id_job' => $chunk->id )
        );

        $stmt->setFetchMode(
                PDO::FETCH_CLASS,
                'Translations_TranslationVersionStruct'
        );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $id_segment
     * @param $version_number
     *
     * @return null|Translations_TranslationVersionStruct
     */
    public static function getVersionNumberForTranslation($id_job, $id_segment, $version_number) {
        $sql = "SELECT * FROM segment_translation_versions " .
                " WHERE id_job = :id_job AND id_segment = :id_segment " .
                " AND version_number = :version_number ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute(
                array( 'id_job' => $id_job,
                       'id_segment' => $id_segment,
                        'version_number' => $version_number
                )
        );

        $stmt->setFetchMode(
                PDO::FETCH_CLASS,
                'Translations_TranslationVersionStruct'
        );

        return $stmt->fetch();
    }

    /**
     * @param $id_job
     * @param $id_segment
     *
     * @return Translations_TranslationVersionStruct[]
     */
    public static function getVersionsForTranslation($id_job, $id_segment) {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job AND id_segment = :id_segment " .
            " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute(
            array( 'id_job' => $id_job, 'id_segment' => $id_segment )
        );

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            'Translations_TranslationVersionStruct'
        );

        return $stmt->fetchAll();
    }

    public function savePropagation($propagation, $id_segment, $job_data) {

        $st_approved   = Constants_TranslationStatus::STATUS_APPROVED;
        $st_rejected   = Constants_TranslationStatus::STATUS_REJECTED;
        $st_translated = Constants_TranslationStatus::STATUS_TRANSLATED;
        $st_new        = Constants_TranslationStatus::STATUS_NEW;
        $st_draft      = Constants_TranslationStatus::STATUS_DRAFT;

        $status_condition = ''; 

        $where_condition = " WHERE " .
            " id_job = :id_job AND " .
            " segment_hash = :segment_hash AND " .
            " id_segment != :id_segment AND " .
            " id_segment BETWEEN :first_segment AND :last_segment " ;

        $where_options = array(
            'id_job'          => $job_data['id'],
            'id_segment'      => $id_segment,
            'first_segment'   => $job_data['job_first_segment'],
            'last_segment'    => $job_data['job_last_segment'],
            'segment_hash'    => $propagation['segment_hash'],
        );

        $this->insertVersionRecords(array(
            'status_condition' => $status_condition,
            'where_condition' => $where_condition,
            'where_options' => $where_options,
            'propagation' => $propagation
        ));

        $this->updateVersionNumberOnFutureUpdates(array(
            'status_condition' => $status_condition,
            'where_condition' => $where_condition,
            'where_options' => $where_options
        ));
    }

    public function saveVersion($old_translation) {
        $sql = "INSERT INTO segment_translation_versions " .
            " ( id_job, id_segment, translation, version_number, time_to_edit ) " .
            " VALUES " .
            " (:id_job, :id_segment, :translation, :version_number, :time_to_edit )";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql );

        return $stmt->execute( array(
            'id_job'         => $old_translation['id_job'],
            'id_segment'     => $old_translation['id_segment'] ,
            'translation'    => $old_translation['translation'],
            'version_number' => $old_translation['version_number'],
            'time_to_edit'   => $old_translation['time_to_edit']
        ));
    }

    private function insertVersionRecords($params) {
        $params = Utils::ensure_keys($params, array(
            'status_condition', 'where_condition', 'where_options'
        ));

        $where_condition = $params['where_condition'];
        $status_condition = $params['status_condition'];
        $where_options = $params['where_options'];
        $propagation = $params['propagation']; // TODO: check this, bug suspect

        /**
         * This query makes and insert while reading from segment_translations.
         * This is done to avoid roundtrips between MySQL and PHP.
         */

        $insert_sql = "INSERT INTO segment_translation_versions " .
            " ( " .
            " id_job, id_segment, translation, version_number, propagated_from " .
            " ) " .
            " SELECT id_job, id_segment, translation, version_number, :propagated_from " .
            " FROM segment_translations " .
            " $where_condition " .
            " $status_condition " ;

        $insert_options = array_merge( $where_options, array(
            'propagated_from' => $propagation['autopropagated_from']
        ));

        $conn = Database::obtain()->getConnection();

        $insert = $conn->prepare( $insert_sql );
        $insert->execute(  $insert_options );

    }

    private function updateVersionNumberOnFutureUpdates($params) {
        $params = Utils::ensure_keys($params, array(
            'status_condition', 'where_condition', 'where_options'
        ));

        $where_condition = $params['where_condition'];
        $status_condition = $params['status_condition'];
        $where_options = $params['where_options'];

        /**
         * Update segment_translations to change the version number
         * for the future changes using the same filter we used for the
         * insert.
         * This is done because we don't want to modify the update SQL
         * in queries.php which is invoked with logic which is not
         * necessarily related to the versioning feature.
         */

        $update_sql = "UPDATE segment_translations " .
            " SET version_number = version_number + 1  " .
            " $where_condition " .
            " $status_condition " ;

        $update_options = $where_options ;

        $conn = Database::obtain()->getConnection();
        $update = $conn->prepare( $update_sql );
        $update->execute( $update_options );
    }

}
