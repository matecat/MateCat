<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use PDO;
use PDOException;
use ReflectionException;
use Utils\Logger\LoggerFactory;
use Utils\Tools\Utils;

class EntryDao extends AbstractDao
{
    /**
     * @param int $id_job
     * @param string $password
     * @param int $revisionNumber
     * @param int|null $idFilePart
     * @param int $ttl
     *
     * @return ShapelessConcreteStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getIssuesGroupedByIdFilePart(int $id_job, string $password, int $revisionNumber, int $idFilePart = null, int $ttl = 0): array
    {
        $conn = $this->database->getConnection();
        $sql = "SELECT
                s.internal_id as content_id,
                s.id as segment_id,
                e.severity as severity_label,
                penalty_points,
                severities,
                options as cat_options,
                label as cat_label
            FROM
                qa_entries e
                    JOIN
                segments s ON s.id = e.id_segment
                    JOIN
                jobs j ON j.id = e.id_job
                JOIN
                qa_categories c ON e.id_category = c.id

                WHERE
                    e.id_job = :id_job
                    AND j.password = :password
                    AND e.source_page = :revisionNumber
                    AND e.deleted_at IS NULL";

        $params = [
            'id_job' => $id_job,
            'password' => $password,
            'revisionNumber' => $revisionNumber
        ];

        if ($idFilePart) {
            $sql .= " AND id_file_part = :id_file_part";
            $params['id_file_part'] = $idFilePart;
        }

        $stmt = $conn->prepare($sql);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $params);
    }

    /**
     * @param array<int, int> $ids
     *
     * @return ShapelessConcreteStruct[]
     * @throws PDOException
     */
    public function getBySegmentIds(array $ids = []): array
    {
        $sql = "SELECT
            q.id_job,
            q.id_segment,
            q.source_page,
            q.id_category,
            q.severity,
            q.translation_version,
            q.penalty_points,
            q.create_date,
            cat.label as cat_label
        FROM
            qa_entries q
                LEFT JOIN
            qa_categories cat ON q.id_category = cat.id
        WHERE
            q.deleted_at IS NULL
                AND q.id_segment IN ( " . implode(', ', $ids) . " ) ";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, ShapelessConcreteStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @throws PDOException
     */
    public function updateRepliesCount(int $id): bool
    {
        $sql = "UPDATE qa_entries SET replies_count = " .
            " ( SELECT count(*) FROM " .
            " qa_entry_comments WHERE id_qa_entry = :id " .
            " ) WHERE id = :id ";

        LoggerFactory::doJsonLog($sql);

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        return $stmt->execute(['id' => $id]);
    }

    /**
     * @throws PDOException
     */
    public function deleteEntry(EntryStruct $record): bool
    {
        $sql = "UPDATE qa_entries SET deleted_at = :deleted_at WHERE id = :id ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        return $stmt->execute([
            'id' => $record->id,
            'deleted_at' => Utils::mysqlTimestamp(time())
        ]);
    }

    /**
     * @param int $id
     *
     * @return ?EntryStruct
     * @throws PDOException
     */
    public function findById(int $id): ?EntryStruct
    {
        $sql = "SELECT qa_entries.*, qa_categories.label AS category " .
            " FROM qa_entries " .
            " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
            " WHERE qa_entries.id = :id AND qa_entries.deleted_at IS NULL LIMIT 1";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, EntryStruct::class);

        return $stmt->fetch() ?: null;
    }

    /**
     * @param JobStruct $chunk
     *
     * @return ShapelessConcreteStruct[]
     * @throws PDOException
     */
    public function findAllByChunk(JobStruct $chunk): array
    {
        $sql = "SELECT qa_entries.*, qa_categories.label as category_label FROM qa_entries
          JOIN segment_translations
            ON segment_translations.id_segment = qa_entries.id_segment
            AND qa_entries.id_job = segment_translations.id_job
          JOIN jobs
            ON jobs.id = qa_entries.id_job
          JOIN qa_categories ON qa_categories.id = qa_entries.id_category
           WHERE
            qa_entries.deleted_at IS NULL AND
            qa_entries.id_job = :id AND jobs.password = :password ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $chunk->id, 'password' => $chunk->password]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, ShapelessConcreteStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @param int $id_segment
     * @param int $id_job
     * @param int $source_page
     *
     * @return EntryWithCategoryStruct[]
     * @throws PDOException
     */
    public function findByIdSegmentAndSourcePage(int $id_segment, int $id_job, int $source_page): array
    {
        $sql = "SELECT qa_entries.*, qa_categories.label as category " .
            " FROM qa_entries " .
            " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
            " WHERE id_job = :id_job AND id_segment = :id_segment " .
            " AND qa_entries.deleted_at IS NULL " .
            " AND qa_entries.source_page = :source_page " .
            " ORDER BY create_date DESC ";

        $opts = [
            'id_segment' => $id_segment,
            'id_job' => $id_job,
            'source_page' => $source_page
        ];

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($opts);

        $stmt->setFetchMode(PDO::FETCH_CLASS, EntryWithCategoryStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @param int $id_segment
     * @param int $id_job
     * @param int $version
     *
     * @return EntryStruct[]
     * @throws PDOException
     */
    public function findAllByTranslationVersion(int $id_segment, int $id_job, int $version): array
    {
        $sql = "SELECT qa_entries.*, qa_categories.label as category " .
            " FROM qa_entries " .
            " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
            " WHERE id_job = :id_job AND id_segment = :id_segment " .
            " AND qa_entries.deleted_at IS NULL " .
            " AND translation_version = :translation_version " .
            " ORDER BY create_date DESC ";

        $opts = [
            'id_segment' => $id_segment,
            'id_job' => $id_job,
            'translation_version' => $version
        ];

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($opts);

        $stmt->setFetchMode(PDO::FETCH_CLASS, EntryStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @param EntryStruct $entryStruct
     *
     * @return EntryStruct
     * @throws PDOException
     */
    public function modifyEntry(EntryStruct $entryStruct): EntryStruct
    {

        $sql  = "UPDATE qa_entries SET
                id_segment=:id_segment,
                id_job=:id_job,
                id_category=:id_category,
                severity=:severity,
                translation_version=:translation_version,
                start_node=:start_node,
                start_offset=:start_offset,
                end_node=:end_node,
                end_offset=:end_offset,
                is_full_segment=:is_full_segment,
                penalty_points=:penalty_points,
                comment=:comment,
                target_text=:target_text,
                uid=:uid,
                source_page=:source_page
                WHERE id = :id;
        ";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        $values = $entryStruct->toArray(
            [
                'id',
                'id_segment',
                'id_job',
                'id_category',
                'severity',
                'translation_version',
                'start_node',
                'start_offset',
                'end_node',
                'end_offset',
                'is_full_segment',
                'penalty_points',
                'comment',
                'target_text',
                'uid',
                'source_page'
            ]
        );

        $stmt->execute($values);

        return $entryStruct;
    }

    /**
     * @param EntryStruct $entryStruct
     *
     * @return EntryStruct
     * @throws PDOException
     */
    public function createEntry(EntryStruct $entryStruct): EntryStruct
    {
        $sql = "INSERT INTO qa_entries
             (
             id_segment, id_job, id_category, severity,
             translation_version, start_node, start_offset,
             end_node, end_offset,
             is_full_segment, penalty_points, comment,
             target_text, uid, source_page
             ) VALUES (
                :id_segment,
                :id_job,
                :id_category,
                :severity,
                :translation_version,
                :start_node,
                :start_offset,
                :end_node,
                :end_offset,
                :is_full_segment,
                :penalty_points,
                :comment,
                :target_text,
                :uid,
                :source_page
             );
        ";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        $values = $entryStruct->toArray(
            [
                'id_segment',
                'id_job',
                'id_category',
                'severity',
                'translation_version',
                'start_node',
                'start_offset',
                'end_node',
                'end_offset',
                'is_full_segment',
                'penalty_points',
                'comment',
                'target_text',
                'uid',
                'source_page'
            ]
        );

        $stmt->execute($values);
        $lastId = (int)$conn->lastInsertId();

        $entryStruct->id = $lastId;

        return $entryStruct;
    }

}
