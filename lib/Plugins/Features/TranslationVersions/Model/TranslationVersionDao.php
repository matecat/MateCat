<?php

namespace Features\TranslationVersions\Model;

use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use DataAccess_IDaoStruct;
use Database;
use PDO;
use Translations_SegmentTranslationStruct;
use Utils;

class TranslationVersionDao extends DataAccess_AbstractDao {

    const TABLE = 'segment_translation_versions';

    protected static $primary_keys = [ 'id_job', 'id_segment', 'version_number' ];

    protected function _buildResult( $array_result ) {
    }

    /**
     * @param $id_job
     *
     * @return array
     */
    public static function getVersionsForJob( $id_job ) {
        $sql = "SELECT * FROM segment_translation_versions " .
                " WHERE id_job = :id_job " .
                " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute(
                [ 'id_job' => $id_job ]
        );

        $stmt->setFetchMode(
                PDO::FETCH_CLASS,
                TranslationVersionStruct::class
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
                [ 'id_job' => $chunk->id ]
        );

        $stmt->setFetchMode(
                PDO::FETCH_CLASS,
                TranslationVersionStruct::class
        );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $id_segment
     * @param $version_number
     *
     * @return null|TranslationVersionStruct
     */
    public function getVersionNumberForTranslation( $id_job, $id_segment, $version_number ) {
        $sql = "SELECT * FROM segment_translation_versions " .
                " WHERE id_job = :id_job AND id_segment = :id_segment " .
                " AND version_number = :version_number ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_job'         => $id_job,
                'id_segment'     => $id_segment,
                'version_number' => $version_number
        ] );

        $stmt->setFetchMode(
                PDO::FETCH_CLASS,
                TranslationVersionStruct::class
        );

        return $stmt->fetch();
    }

    /**
     * @param      $id_job
     * @param      $id_segment
     *
     * @param null $version_number
     *
     * @return TranslationVersionStruct[]
     */
    public static function getVersionsForTranslation( $id_job, $id_segment, $version_number = null ) {
        $sql = "SELECT * FROM segment_translation_versions " .
                " WHERE id_job = :id_job AND id_segment = :id_segment ";
        $params = [ 'id_job' => $id_job, 'id_segment' => $id_segment ];

        if($version_number !== null){
            $sql .= ' AND version_number = :version_number';
            $params['version_number'] = $version_number;
        }

        $sql .= " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute($params);

        $stmt->setFetchMode(
                PDO::FETCH_CLASS,
                TranslationVersionStruct::class
        );

        return $stmt->fetchAll();
    }


    /**
     * @param $id_job
     * @param $id_segment
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getVersionsForRevision( $id_job, $id_segment ) {

        $sql = "SELECT * FROM (

    -- Query for data from current version

    SELECT

    0 as id,
    st.id_segment,
    st.id_job,
    st.translation,
    st.version_number,
    st.translation_date AS creation_date,
    st.autopropagated_from AS propagated_from,
    st.time_to_edit,
    stv.raw_diff,

    qa.id as qa_id,
    qa.comment as qa_comment,
    qa.create_date as qa_create_date,
    qa.id_category as qa_id_category,
    qa.id_job as qa_id_job,
    qa.id_segment as qa_id_segment,
    qa.is_full_segment as qa_is_full_segment,
    qa.severity as qa_severity,
    qa.start_node as qa_start_node,
    qa.start_offset as qa_start_offset,
    qa.end_node as qa_end_node,
    qa.end_offset as qa_end_offset,
    qa.translation_version as qa_translation_version,
    qa.target_text as qa_target_text,
    qa.penalty_points as qa_penalty_points,
    qa.rebutted_at as qa_rebutted_at,
    qa.source_page as qa_source_page

    FROM segment_translations st LEFT JOIN qa_entries qa
        ON st.id_segment = qa.id_segment AND st.id_job = qa.id_job AND
          st.version_number = qa.translation_version
          AND qa.deleted_at IS NULL

        LEFT JOIN segment_translation_versions AS stv
          ON stv.id_job = st.id_job AND stv.id_segment = st.id_segment
          AND st.version_number = stv.version_number
        WHERE st.id_job = :id_job AND st.id_segment = :id_segment
    ) t1

  UNION SELECT * FROM (

    -- Query for data from previous versions

     SELECT

    stv.id,
    stv.id_segment,
    stv.id_job,
    stv.translation,
    stv.version_number,
    stv.creation_date,
    stv.propagated_from,
    stv.time_to_edit,
    stv.raw_diff,

     qa.id as qa_id,
     qa.comment as qa_comment,
     qa.create_date as qa_create_date ,
     qa.id_category as qa_id_category,
     qa.id_job as qa_id_job,
     qa.id_segment as qa_id_segment,
     qa.is_full_segment as qa_is_full_segment,
     qa.severity as qa_severity,
     qa.start_node as qa_start_node,
     qa.start_offset as qa_start_offset,
     qa.end_node as qa_end_node,
     qa.end_offset as qa_end_offset,
     qa.translation_version as qa_translation_version,
     qa.target_text as qa_target_text,
     qa.penalty_points as qa_penalty_points,
     qa.rebutted_at as qa_rebutted_at,
     qa.source_page as qa_source_page

    FROM segment_translation_versions stv 
    
    LEFT JOIN segment_translations st 
		ON st.id_segment = stv.id_segment 
			AND st.id_job = stv.id_job 
			AND st.version_number = stv.version_number
			
    LEFT JOIN qa_entries qa
        ON stv.id_job = qa.id_job AND stv.id_segment = qa.id_segment
          AND stv.version_number = qa.translation_version
          AND qa.deleted_at IS NULL
        WHERE stv.id_job = :id_job AND stv.id_segment = :id_segment AND st.id_segment IS NULL 
    ) t2

    ORDER BY version_number DESC
    ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $this->_fetchObject( $stmt,
                ( new ShapelessConcreteStruct() ),
                [ 'id_job' => $id_job, 'id_segment' => $id_segment ]
        );

    }

    /**
     * @param      $segments_id
     * @param      $job_id
     * @param null $source_page
     *
     * @return array
     */
    public function getLastRevisionsBySegmentsAndSourcePage( $segments_id, $job_id, $source_page ) {

        $db = Database::obtain()->getConnection();

        $prepare_str_segments_id = str_repeat( ' ?, ', count( $segments_id ) - 1 ) . " ?";

        $query = "SELECT 

    stv.id_segment,
    stv.translation,
    ste.version_number

    FROM
        (
            SELECT id_segment, translation, version_number, id_job
            FROM segment_translation_versions
            WHERE id_segment IN (
                $prepare_str_segments_id
            )
            AND id_job = ?
            UNION
            SELECT id_segment, translation, version_number, id_job
            FROM segment_translations
            WHERE id_segment IN (
                $prepare_str_segments_id
            )
            AND id_job = ?
        ) stv
    JOIN
        (
                SELECT
                    MAX(version_number) AS version_number, ste.id_segment
                FROM
                    segment_translation_events ste

                WHERE id_segment IN (
                    $prepare_str_segments_id
                )
                AND ste.id_job = ?
                AND ste.source_page = ?

                GROUP BY id_segment

        ) AS ste ON stv.version_number = ste.version_number AND stv.id_segment = ste.id_segment";

        $stmt = $db->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );
        $stmt->execute( array_merge( $segments_id, [ $job_id ], $segments_id, [ $job_id ], $segments_id, [ $job_id ], [ $source_page ] ) );

        $results = $stmt->fetchAll();

        return $results;


    }

    public function savePropagationVersions( Translations_SegmentTranslationStruct $propagation, $id_segment, Chunks_ChunkStruct $job_data, $propagated_ids ) {

        $status_condition           = '';
        $propagated_ids_placeholder = [];

        for ( $i = 1; $i <= count( $propagated_ids ); $i++ ) {
            $propagated_ids_placeholder[] = ':propagated_id_' . $i;
        }

        $propagated_ids_placeholder = implode( ',', $propagated_ids_placeholder );

        $where_condition = " WHERE " .
                " id_job = :id_job AND " .
                " segment_hash = :segment_hash AND " .
                " id_segment != :id_segment AND " .
                " id_segment IN (" . $propagated_ids_placeholder . ") ";

        $where_options = [
                'id_job'         => $job_data[ 'id' ],
                'id_segment'     => $id_segment,
                'propagated_ids' => $propagated_ids,
                'segment_hash'   => $propagation->segment_hash,

        ];

        $this->insertVersionRecords( [
                'status_condition' => $status_condition,
                'where_condition'  => $where_condition,
                'where_options'    => $where_options,
                'propagation'      => $propagation,
        ] );

        $this->upCountVersionNumberOnPropagatedTranslations( [
                'status_condition' => $status_condition,
                'where_condition'  => $where_condition,
                'where_options'    => $where_options
        ] );
    }

    public function saveVersion( TranslationVersionStruct $new_version ) {
        $sql = "INSERT INTO segment_translation_versions " .
                " ( id_job, id_segment, translation, version_number, time_to_edit, old_status, new_status ) " .
                " VALUES " .
                " (:id_job, :id_segment, :translation, :version_number, :time_to_edit, :old_status, :new_status ) ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( [
                'id_job'         => $new_version->id_job,
                'id_segment'     => $new_version->id_segment,
                'translation'    => $new_version->translation,
                'version_number' => $new_version->version_number,
                'time_to_edit'   => $new_version->time_to_edit,
                'old_status'     => $new_version->old_status,
                'new_status'     => $new_version->new_status,
        ] );
    }

    public function updateVersion( TranslationVersionStruct $old_translation ) {
        $sql = "UPDATE segment_translation_versions
                SET translation = :translation, time_to_edit = :time_to_edit
                WHERE id_job = :id_job AND id_segment = :id_segment
                AND version_number = :version_number ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_job'         => $old_translation->id_job,
                'id_segment'     => $old_translation->id_segment,
                'translation'    => $old_translation->translation,
                'version_number' => $old_translation->version_number,
                'time_to_edit'   => $old_translation->time_to_edit
        ] );

        return $stmt->rowCount();
    }

    private function insertVersionRecords( $params ) {
        $params = Utils::ensure_keys( $params, [
                'status_condition', 'where_condition', 'where_options'
        ] );

        $where_condition  = $params[ 'where_condition' ];
        $status_condition = $params[ 'status_condition' ];
        $where_options    = $params[ 'where_options' ];
        $propagation      = $params[ 'propagation' ]; // TODO: check this, bug suspect

        $select_sql =
                " SELECT id_job, id_segment, translation, version_number, :propagated_from " .
                " FROM segment_translations " .
                " $where_condition " .
                " $status_condition ";

        $select_ids_map = [];
        foreach ( $where_options[ 'propagated_ids' ] as $key => $propagated_id ) {
            $select_ids_map[ 'propagated_id_' . ( $key + 1 ) ] = $propagated_id;
        }

        unset( $where_options[ 'propagated_ids' ] );

        $select_options = array_merge(
                $where_options,
                [ 'propagated_from' => $propagation[ 'autopropagated_from' ] ],
                $select_ids_map
        );

        $conn = Database::obtain()->getConnection();

        $select = $conn->prepare( $select_sql );
        $select->execute( $select_options );

        $propagated_segments = $select->fetchAll();

        $insert_value_map = [];

        foreach ( $propagated_segments as $propagated_segment ) {
            $insert_value_map[] = [
                    $propagated_segment[ 'id_job' ],
                    $propagated_segment[ 'id_segment' ],
                    $propagated_segment[ 'translation' ],
                    $propagated_segment[ 'version_number' ],
            ];
        }

        $chunk_size = 200;
        $chunks     = array_chunk( $insert_value_map, $chunk_size, true );

        for ( $k = 0; $k < count( $chunks ); $k++ ) {

            $insert_sql = "INSERT INTO segment_translation_versions " .
                    " ( " .
                    " id_job, id_segment, translation, version_number, propagated_from " .
                    " ) VALUES ";

            $insert_placeholders = [];
            $insert_values       = [];

            foreach ( $chunks[ $k ] as $key => $chunk ) {
                $insert_placeholders[] = "(:id_job_" . $key . ", :id_segment_" . $key . ", :translation_" . $key . ", :version_number_" . $key . ", :propagated_from_" . $key . ")";

                $current_value                              = $insert_value_map[ ( $key ) ];
                $insert_values[ 'id_job_' . $key ]          = $current_value[ 0 ];
                $insert_values[ 'id_segment_' . $key ]      = $current_value[ 1 ];
                $insert_values[ 'translation_' . $key ]     = $current_value[ 2 ];
                $insert_values[ 'version_number_' . $key ]  = $current_value[ 3 ];
                $insert_values[ 'propagated_from_' . $key ] = $propagation[ 'autopropagated_from' ];
            }

            $insert_sql .= implode( ',', $insert_placeholders );

            $select = $conn->prepare( $insert_sql );
            $select->execute( $insert_values );
        }
    }

    private function upCountVersionNumberOnPropagatedTranslations( $params ) {
        $params = Utils::ensure_keys( $params, [
                'status_condition', 'where_condition', 'where_options'
        ] );

        $where_condition  = $params[ 'where_condition' ];
        $status_condition = $params[ 'status_condition' ];
        $where_options    = $params[ 'where_options' ];

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
                " $status_condition ";

        $update_options                   = [];
        $update_options[ 'id_job' ]       = $where_options[ 'id_job' ];
        $update_options[ 'id_segment' ]   = $where_options[ 'id_segment' ];
        $update_options[ 'segment_hash' ] = $where_options[ 'segment_hash' ];

        for ( $i = 1; $i <= count( $where_options[ 'propagated_ids' ] ); $i++ ) {
            $update_options[ 'propagated_id_' . $i ] = $where_options[ 'propagated_ids' ][ ( $i - 1 ) ];
        }

        $conn   = Database::obtain()->getConnection();
        $update = $conn->prepare( $update_sql );
        $update->execute( $update_options );
    }

}
