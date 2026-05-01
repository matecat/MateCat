<?php

namespace Plugins\Features\TranslationVersions\Model;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\QualityReport\SegmentEventsStruct;
use Model\Translations\SegmentTranslationStruct;
use PDO;
use PDOException;
use Exception;
use Utils\Tools\Utils;

class TranslationVersionDao extends AbstractDao
{

    const string TABLE = 'segment_translation_versions';

    protected static array $primary_keys = ['id_job', 'id_segment', 'version_number'];

    /**
     * @return list<TranslationVersionStruct>
     * @throws PDOException
     */
    public static function getVersionsForJob(int $id_job): array
    {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job " .
            " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->execute(
            ['id_job' => $id_job]
        );

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            TranslationVersionStruct::class
        );

        /** @var list<TranslationVersionStruct> */
        return $stmt->fetchAll();
    }

    /**
     * @return list<TranslationVersionStruct>
     * @throws PDOException
     */
    public static function getVersionsForChunk(JobStruct $chunk): array
    {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job " .
            " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->execute(
            ['id_job' => $chunk->id]
        );

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            TranslationVersionStruct::class
        );

        /** @var list<TranslationVersionStruct> */
        return $stmt->fetchAll();
    }

    /**
     * @return TranslationVersionStruct|false
     * @throws PDOException
     */
    public function getVersionNumberForTranslation(int $id_job, int $id_segment, int $version_number): TranslationVersionStruct|false
    {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job AND id_segment = :id_segment " .
            " AND version_number = :version_number ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->execute([
            'id_job' => $id_job,
            'id_segment' => $id_segment,
            'version_number' => $version_number
        ]);

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            TranslationVersionStruct::class
        );

        return $stmt->fetch();
    }

    /**
     * @return list<TranslationVersionStruct>
     * @throws PDOException
     */
    public static function getVersionsForTranslation(int $id_job, int $id_segment, ?int $version_number = null): array
    {
        $sql = "SELECT * FROM segment_translation_versions " .
            " WHERE id_job = :id_job AND id_segment = :id_segment ";
        $params = ['id_job' => $id_job, 'id_segment' => $id_segment];

        if ($version_number !== null) {
            $sql .= ' AND version_number = :version_number';
            $params['version_number'] = $version_number;
        }

        $sql .= " ORDER BY creation_date DESC ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->execute($params);

        $stmt->setFetchMode(
            PDO::FETCH_CLASS,
            TranslationVersionStruct::class
        );

        /** @var list<TranslationVersionStruct> */
        return $stmt->fetchAll();
    }


    /**
     * Fetches translation versions combined with QA entries for the revision view.
     *
     * Note: parameters are intentionally typed as int|string because callers
     * may pass request params directly (string) or struct properties (int).
     *
     * @return list<IDaoStruct>
     * @throws PDOException
     * @throws Exception
     */
    public function getVersionsForRevision(int|string $id_job, int|string $id_segment): array
    {
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

    qa.uid as qa_uid,
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

     qa.uid as qa_uid,
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
        $stmt = $conn->prepare($sql);

        return $this->_fetchObjectMap(
            $stmt,
            ShapelessConcreteStruct::class,
            ['id_job' => $id_job, 'id_segment' => $id_segment]
        );
    }

    /**
     * @param list<int> $segments_id
     *
     * @return list<SegmentEventsStruct>
     * @throws PDOException
     */
    public function getAllRelevantEvents(array $segments_id, int $job_id): array
    {
        $db = Database::obtain()->getConnection();

        $prepare_str_segments_id = implode(', ', array_fill(0, count($segments_id), '?'));

        $query = "
            SELECT
                stv.id_segment,
                stv.translation,
                ste.version_number,
                ste.source_page
            FROM (
                 SELECT id_segment, translation, version_number, id_job
                 FROM segment_translation_versions
                 WHERE id_segment IN ( $prepare_str_segments_id )
                   AND id_job = ?
                 UNION
                 SELECT id_segment, translation, version_number, id_job
                 FROM segment_translations
                 WHERE id_segment IN ( $prepare_str_segments_id )
                   AND id_job = ?
            ) AS stv
            JOIN (
                   SELECT MAX(version_number) AS version_number, ste.id_segment, ste.source_page
                   FROM segment_translation_events ste
                   WHERE id_segment IN ( $prepare_str_segments_id )
                     AND ste.id_job = ?
                     AND IF( source_page > 1 , final_revision = 1 , source_page = 1 )
                   GROUP BY id_segment, ste.source_page
            ) AS ste ON stv.version_number = ste.version_number AND stv.id_segment = ste.id_segment;
";

        $stmt = $db->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_CLASS, SegmentEventsStruct::class);
        $stmt->execute(array_merge($segments_id, [$job_id], $segments_id, [$job_id], $segments_id, [$job_id]));

        /** @var list<SegmentEventsStruct> */
        return $stmt->fetchAll();
    }

    /**
     * Saves version records for propagated translations.
     *
     * @param SegmentTranslationStruct $propagatorSegment The segment that initiated propagation
     * @param int $id_segment The source segment ID
     * @param JobStruct $job_data The job struct (accessed via ArrayAccess)
     * @param list<SegmentTranslationStruct> $segmentsToUpdate Segments that were propagated
     *
     * @throws PDOException
     * @throws Exception
     */
    public function savePropagationVersions(SegmentTranslationStruct $propagatorSegment, int $id_segment, JobStruct $job_data, array $segmentsToUpdate): void
    {
        $chunked_segments_list = array_chunk($segmentsToUpdate, 20, true);

        foreach ($chunked_segments_list as $segments) {
            $where_options = [
                'id_job' => $job_data['id'],
                'id_segment' => $id_segment,
                'propagated_segments' => array_values($segments) /* reset the keys */,
                'autopropagated_from' => $propagatorSegment['autopropagated_from']
            ];

            $this->insertVersionRecords([
                'where_options' => $where_options,
            ]);
        }
    }

    /**
     * @throws PDOException
     */
    public function saveVersion(TranslationVersionStruct $new_version): bool
    {
        $sql = "INSERT INTO segment_translation_versions " .
            " ( id_job, id_segment, translation, version_number, time_to_edit, old_status, new_status ) " .
            " VALUES " .
            " (:id_job, :id_segment, :translation, :version_number, :time_to_edit, :old_status, :new_status ) ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        return $stmt->execute([
            'id_job' => $new_version->id_job,
            'id_segment' => $new_version->id_segment,
            'translation' => $new_version->translation,
            'version_number' => $new_version->version_number,
            'time_to_edit' => $new_version->time_to_edit,
            'old_status' => $new_version->old_status,
            'new_status' => $new_version->new_status,
        ]);
    }

    /**
     * @throws PDOException
     */
    public function updateVersion(TranslationVersionStruct $old_translation): int
    {
        $sql = "UPDATE segment_translation_versions
                SET translation = :translation, time_to_edit = :time_to_edit
                WHERE id_job = :id_job AND id_segment = :id_segment
                AND version_number = :version_number ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        $stmt->execute([
            'id_job' => $old_translation->id_job,
            'id_segment' => $old_translation->id_segment,
            'translation' => $old_translation->translation,
            'version_number' => $old_translation->version_number,
            'time_to_edit' => $old_translation->time_to_edit
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param array{where_options: array{id_job: mixed, id_segment: int, propagated_segments: list<SegmentTranslationStruct>, autopropagated_from: mixed}} $params
     *
     * @throws PDOException
     * @throws Exception
     */
    private function insertVersionRecords(array $params): void
    {
        $params = Utils::ensure_keys($params, ['where_options']);

        $where_options = $params['where_options'];

        $insert_value_list = [];

        foreach ($where_options['propagated_segments'] as $propagated_segment) {
            $insert_value_list[] = [
                $propagated_segment['id_job'],
                $propagated_segment['id_segment'],
                $propagated_segment['translation'],
                $propagated_segment['version_number'],
            ];
        }

        $insert_sql = "INSERT INTO segment_translation_versions " .
            " ( " .
            " id_job, id_segment, translation, version_number, propagated_from " .
            " ) VALUES ";

        $insert_placeholders = [];
        $insert_values = [];

        foreach ($insert_value_list as $key => $_insert_values) {
            $insert_placeholders[] = "(:id_job_" . $key . ", :id_segment_" . $key . ", :translation_" . $key . ", :version_number_" . $key . ", :propagated_from_" . $key . ")";

            $current_value = $_insert_values;
            $insert_values['id_job_' . $key] = $current_value[0];
            $insert_values['id_segment_' . $key] = $current_value[1];
            $insert_values['translation_' . $key] = $current_value[2];
            $insert_values['version_number_' . $key] = $current_value[3];
            $insert_values['propagated_from_' . $key] = $where_options['autopropagated_from'];
        }

        $insert_sql .= implode(',', $insert_placeholders);

        $conn = Database::obtain()->getConnection();
        $insert = $conn->prepare($insert_sql);
        $insert->execute($insert_values);
        $insert->closeCursor();
    }

}
