<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/15/16
 * Time: 2:39 PM
 */

namespace Features\ReviewExtended\Model;

use Constants;
use DataAccess_AbstractDao;
use Database;

class QualityReportDao extends DataAccess_AbstractDao {

    protected function _buildResult( $result_array ) {

    }


    public function getAverages( \Chunks_ChunkStruct $chunk ) {
        $sql = <<<SQL

      SELECT
        ROUND( AVG( time_to_edit ) ) AS avg_time_to_edit,
        ROUND( AVG( edit_distance ) ) AS avg_edit_distance

      FROM segment_translations st

      JOIN jobs
        ON jobs.id = st.id_job
        AND jobs.password = :password
        AND jobs.id = :id_job

      JOIN segments s
        ON s.id = st.id_segment
        AND s.id >= jobs.job_first_segment
        AND s.id <= jobs.job_last_segment

      JOIN files_job fj
        ON st.id_job = fj.id_job
           AND s.id_file = fj.id_file

      JOIN files f ON f.id = fj.id_file

        WHERE show_in_cattool
SQL;

        $conn  = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_ASSOC );

        $stmt->execute( array(
            'id_job'   => $chunk->id,
            'password' => $chunk->password
        ) );

        return $stmt->fetch();

    }
    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     */
    public static function getSegmentsForQualityReport( \Chunks_ChunkStruct $chunk ) {

        $sql = <<<SQL

SELECT

  f.id AS file_id,
  f.filename AS file_filename,

  otv.translation AS original_translation,

  st.version_number,
  st.translation as translation,
  st.status as translation_status,
  st.edit_distance as edit_distance,
  st.time_to_edit  as time_to_edit,

  s.id AS segment_id,
  s.segment AS segment_source,

  comments.id as comment_id,
  comments.comment as comment_comment,
  comments.create_date as comment_create_date,
  comments.uid as comment_uid,

  issues.id as issue_id,
  issues.create_date as issue_create_date,
  issues.replies_count as issue_replies_count,

  -- start_offset and end_offset were introduced for DQF. We are taking for granted a string with
  -- both start_node and end_node equal to 0 ( no tags in target string ).
  issues.start_offset  as issue_start_offset,
  issues.end_offset    as issue_end_offset,

  qa_categories.label   as issue_category,
  qa_categories.options as category_options,

  issues.severity     as issue_severity,
  issues.comment      as issue_comment,
  issues.target_text  as target_text,
  issues.uid          as issue_uid

FROM segment_translations st

  JOIN jobs
    ON jobs.id = st.id_job
    AND jobs.password = :password
    AND jobs.id = :id_job

  JOIN segments s
    ON s.id = st.id_segment
    AND s.id >= jobs.job_first_segment
    AND s.id <= jobs.job_last_segment

  JOIN files_job fj
    ON st.id_job = fj.id_job
       AND s.id_file = fj.id_file

  JOIN files f ON f.id = fj.id_file

  LEFT JOIN segment_translation_versions otv
    ON otv.id_segment = st.id_segment
       AND otv.version_number = 0

  LEFT JOIN qa_entries issues
    ON issues.id_job = jobs.id
    AND issues.id_segment = s.id
    AND issues.translation_version = st.version_number
    AND issues.deleted_at IS NULL

  LEFT JOIN qa_entry_comments comments
    ON comments.id_qa_entry = issues.id

  LEFT JOIN qa_categories
    ON issues.id_category = qa_categories.id

WHERE

s.show_in_cattool AND
(
  st.status IN ( :approved, :rejected )
)

ORDER BY f.id, s.id, issues.id, comments.id

;

SQL;

        $conn  = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_ASSOC );

        $stmt->execute( array(
                'approved' => \Constants_TranslationStatus::STATUS_APPROVED,
                'rejected' => \Constants_TranslationStatus::STATUS_REJECTED,
                'id_job'   => $chunk->id,
                'password' => $chunk->password
        ) );

        return $stmt->fetchAll();

    }

    /**
     * @param $segments_id array
     * @param $job_id integer
     *
     * @return array
     */
    public static function getIssuesBySegments( $segments_id, $job_id ) {

        $prepare_str_segments_id = str_repeat( 'UNION SELECT ? ', count( $segments_id ) - 1);

        $sql = "SELECT

  issues.id_segment as segment_id,
  issues.id as issue_id,
  issues.create_date as issue_create_date,
  issues.replies_count as issue_replies_count,
  issues.source_page as source_page,

  -- start_offset and end_offset were introduced for DQF. We are taking for granted a string with
  -- both start_node and end_node equal to 0 ( no tags in target string ).
  issues.start_offset  as issue_start_offset,
  issues.end_offset    as issue_end_offset,

  qa_categories.label   as issue_category,
  qa_categories.options as category_options,

  issues.severity     as issue_severity,
  issues.comment      as issue_comment,
  issues.target_text  as target_text,
  issues.uid          as issue_uid,

  translation_warnings.scope as warning_scope,
  translation_warnings.data as warning_data,
  translation_warnings.severity as warning_severity

FROM  qa_entries issues

JOIN (
		SELECT ? as id_segment
		" . $prepare_str_segments_id . "
) AS SLIST USING( id_segment )

  LEFT JOIN qa_categories
    ON issues.id_category = qa_categories.id

  LEFT JOIN translation_warnings
    ON translation_warnings.id_segment = issues.id_segment

    WHERE issues.deleted_at IS NULL AND issues.id_job = ?

  ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );

        $stmt->execute( array_merge($segments_id, array($job_id)) );

        return $stmt->fetchAll();
    }

    public function getReviseIssuesByChunk($job_id, $password, $source_page = null ) {

        if ( is_null( $source_page ) ) {
            $source_page = Constants::SOURCE_PAGE_REVISION  ;
        }

        $sql = "SELECT
  issues.id             AS issue_id,
  qa_categories.label   AS issue_category_label,
  issues.id_category    AS id_category,
  issues.severity       AS issue_severity

FROM  qa_entries issues

JOIN jobs j ON issues.id_job = j.id
    AND issues.id_segment >= j.job_first_segment
    AND issues.id_segment <= j.job_last_segment

  LEFT JOIN qa_categories
    ON issues.id_category = qa_categories.id

    WHERE j.id = ? AND j.password = ?
      AND issues.source_page = ?
      AND issues.deleted_at IS NULL
  ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );

        $stmt->execute( array( $job_id, $password, $source_page ) );

        return $stmt->fetchAll();

    }



}