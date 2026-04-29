<?php

namespace Model\ProjectCreation;

use Exception;
use Model\Concerns\LogsMessages;
use Model\DataAccess\IDatabase;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDO;
use PDOException;
use PDOStatement;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

class ProjectManagerModel
{
    use LogsMessages;

    private IDatabase $dbHandler;

    public function __construct(IDatabase $dbHandler, MatecatLogger $logger)
    {
        $this->dbHandler = $dbHandler;
        $this->logger = $logger;
    }

    /**
     * Creates a record in the projects table and instantiates the project struct
     * internally.
     *
     * @throws Exception
     */
    public function createProjectRecord(ProjectStructure $projectStructure, ?int $idTeam, string $status, ?int $idAssignee): ProjectStruct
    {
        $data = [];
        $data['id'] = $projectStructure->id_project;
        $data['id_customer'] = $projectStructure->id_customer;
        $data['id_team'] = $idTeam;
        $data['name'] = $projectStructure->project_name;
        $data['create_date'] = $projectStructure->create_date;
        $data['status_analysis'] = $status;
        $data['password'] = $projectStructure->ppassword;
        $data['pretranslate_100'] = $projectStructure->pretranslate_100;
        $data['remote_ip_address'] = empty($projectStructure->user_ip) ? 'UNKNOWN' : $projectStructure->user_ip;
        $data['id_assignee'] = $idAssignee;
        $data['instance_id'] = $projectStructure->instance_id ?? AppConfig::$INSTANCE_ID;
        $data['due_date'] = $projectStructure->due_date;

        $this->dbHandler->begin();

        try {
            $projectId = $this->dbHandler->insert('projects', $data);
            $project = ProjectDao::findById($projectId);
            $this->dbHandler->commit();
        } catch (Exception $e) {
            $this->dbHandler->rollback();
            throw $e;
        }

        if ($project === null) {
            throw new RuntimeException("Failed to retrieve project after insert (id: $projectId)");
        }

        return $project;
    }

    /**
     * @throws Exception
     */
    public function insertFile(int $idProject, string $sourceLanguage, string $file_name, string $fileExtension, string $fileDateSha1Path): string
    {
        $data = [];
        $data['id_project'] = $idProject;
        $data['filename'] = $file_name;
        $data['source_language'] = $sourceLanguage;
        $data['mime_type'] = $fileExtension;
        $data['sha1_original_file'] = $fileDateSha1Path;
        $data['is_converted'] = 0;

        try {
            $idFile = $this->dbHandler->insert('files', $data);
        } catch (PDOException $e) {
            $this->log("Database insert error: {$e->getMessage()} ", $e);
            throw new Exception("Database insert file error: {$e->getMessage()} ", -$e->getCode());
        }

        return $idFile;
    }

    /**
     * @param list<array<string, mixed>> $query_translations_values
     * @throws PDOException
     */
    public function insertPreTranslations(array $query_translations_values): void
    {
        $baseQuery = "
                INSERT INTO segment_translations (
                        id_segment, 
                        id_job, 
                        segment_hash, 
                        status, 
                        translation, 
                        suggestion,
                        translation_date, /* NOW() */
                        tm_analysis_status, /* SKIPPED */
                        locked, 
                        match_type, 
                        eq_word_count,
                        serialized_errors_list,
                        warning,
                        suggestion_match,
                        standard_word_count,
                        version_number
                )
                VALUES ";

        $tupleMarks = "( ?, ?, ?, ?, ?, ?, NOW(), 'SKIPPED', ?, ?, ?, ?, ?, ?, ?, ? )";

        $this->executeBulkInsert($baseQuery, $tupleMarks, $query_translations_values, 100, 'Pre-Translations', ProjectCreationError::BULK_INSERT_PRE_TRANSLATIONS->value);
    }

    /**
     * Executes a chunked bulk INSERT, flattening each chunk of tuples into
     * positional PDO parameters.
     *
     * @param string $insertTemplate SQL up to and including "VALUES "
     * @param string $tupleMarks Placeholder tuple, e.g. "( ?, ?, ? )"
     * @param list<array<int|string, mixed>> $insertValues Rows — each element is a flat tuple of column values
     * @param positive-int $chunkSize Max rows per INSERT statement
     * @param string $label Human-readable label for logging/errors
     * @param int $errorCode Code to attach to re-thrown PDOException
     *
     * @throws PDOException
     */
    private function executeBulkInsert(
        string $insertTemplate,
        string $tupleMarks,
        array $insertValues,
        int $chunkSize,
        string $label,
        int $errorCode
    ): void {
        $this->log("$label: Total Rows to insert: " . count($insertValues));

        $chunked = array_chunk($insertValues, $chunkSize);
        $this->log("$label: Total Queries to execute: " . count($chunked));

        $conn = $this->dbHandler->getConnection();
        $stmt = null;
        $flattenedValues = [];

        foreach ($chunked as $i => $chunk) {
            try {
                $query = $insertTemplate . implode(', ', array_fill(0, count($chunk), $tupleMarks));
                $stmt = $conn->prepare($query);
                $flattenedValues = iterator_to_array(
                    new RecursiveIteratorIterator(new RecursiveArrayIterator($chunk)),
                    false
                );
                $stmt->execute($flattenedValues);

                $this->log("$label: Executed Query " . ($i + 1));
            } catch (PDOException $e) {
                $this->log("$label import - DB Error: " . $e->getMessage(), $e);
                $this->log("$label import - Statement: " . ($stmt instanceof PDOStatement ? $stmt->queryString : 'N/A'));
                $this->log("$label Chunk Dump: " . var_export($chunk, true));
                $this->log("$label Flattened Values Dump: " . var_export($flattenedValues, true));
                throw new PDOException("$label import - DB Error: " . $e->getMessage(), $errorCode, $e);
            }
        }
    }

    /**
     * Single-pass classification and bulk insert of segment notes and metadata.
     *
     * Iterates through $notes once, classifying each entry as either a segment
     * note (INSERT INTO segment_notes) or segment metadata (INSERT INTO
     * segment_metadata) based on the attribute key.
     *
     * @param array<int|string, array<string, mixed>> $notes
     *
     * @throws PDOException
     */
    public function bulkInsertSegmentNotesAndMetadata(array $notes): void
    {
        $noteTemplate = " INSERT INTO segment_notes ( id_segment, internal_id, note, json ) VALUES ";
        $noteTupleMarks = "( ?, ?, ?, ? )";

        $metaTemplate = " INSERT INTO segment_metadata ( id_segment, meta_key, meta_value ) VALUES ";
        $metaTupleMarks = "( ?, ?, ? )";

        $noteValues = [];
        $metaValues = [];

        foreach ($notes as $internalId => $v) {
            $attributes = $v['from'];
            $entries = $v['entries'];
            $segments = $v['segment_ids'];
            $jsonEntries = $v['json'];
            $jsonSegmentIds = $v['json_segment_ids'];

            // Text entries
            foreach ($segments as $idSegment) {
                foreach ($entries as $index => $note) {
                    if (isset($attributes['entries'][$index])) {
                        $metaKey = Utils::stripTagsPreservingHrefs(html_entity_decode($attributes['entries'][$index]));

                        if (self::isAMetadata($metaKey)) {
                            $metaValue = Utils::stripTagsPreservingHrefs(html_entity_decode($note));
                            $metaValues[] = [$idSegment, $metaKey, $metaValue];
                        } else {
                            $noteValues[] = [$idSegment, $internalId, Utils::stripTagsPreservingHrefs(html_entity_decode($note)), null];
                        }
                    } else {
                        $noteValues[] = [$idSegment, $internalId, Utils::stripTagsPreservingHrefs(html_entity_decode($note)), null];
                    }
                }
            }

            // JSON entries
            foreach ($jsonSegmentIds as $idSegment) {
                foreach ($jsonEntries as $index => $json) {
                    if (isset($attributes['json'][$index])) {
                        $metaKey = $attributes['json'][$index];

                        if (self::isAMetadata($metaKey)) {
                            $metaValues[] = [$idSegment, $metaKey, $json];
                        } else {
                            $noteValues[] = [$idSegment, $internalId, null, $json];
                        }
                    } else {
                        $noteValues[] = [$idSegment, $internalId, null, $json];
                    }
                }
            }
        }

        if ($noteValues !== []) {
            $this->executeBulkInsert($noteTemplate, $noteTupleMarks, $noteValues, 30, 'Notes', ProjectCreationError::BULK_INSERT_NOTES->value);
        }

        if ($metaValues !== []) {
            $this->executeBulkInsert($metaTemplate, $metaTupleMarks, $metaValues, 30, 'Segment Metadata', ProjectCreationError::BULK_INSERT_SEGMENT_METADATA->value);
        }
    }

    /**
     * @param string $metaKey
     * @return bool
     */
    private static function isAMetadata(string $metaKey): bool
    {
        $metaDataKeys = [
            'id_request',
            'id_content',
            'id_order',
            'id_order_group',
            'screenshot'
        ];

        return in_array($metaKey, $metaDataKeys);
    }

    /**
     * @param array<int|string, array<string, mixed>> $contextGroups
     *
     * @throws PDOException
     */
    public function bulkInsertContextsGroups(int $idProject, array $contextGroups): void
    {
        $template = " INSERT INTO context_groups ( id_project, id_segment, context_json ) VALUES ";
        $tupleMarks = "( ?, ?, ? )";

        $insert_values = [];

        foreach ($contextGroups as $v) {
            $context_json = json_encode($v['context_json']);
            $segments = $v['context_json_segment_ids'];

            foreach ($segments as $id_segment) {
                $insert_values[] = [$idProject, $id_segment, $context_json];
            }
        }

        $this->executeBulkInsert($template, $tupleMarks, $insert_values, 30, 'Context Groups', ProjectCreationError::BULK_INSERT_CONTEXT_GROUPS->value);
    }

    /**
     * Performs a full cascade deletion of a project and all its related data.
     *
     * Deletion order mirrors the production cleanup script to respect implicit
     * dependency ordering (no FK CASCADE constraints exist in the database).
     *
     * Phase 1 — Job-scoped:
     *   comments, qa_chunk_reviews, segment_translation_events,
     *   segment_translation_versions, segment_translations (batched),
     *   files_job, job_metadata
     *
     * Phase 2 — Segment-scoped (per-job range):
     *   segment_metadata, segment_notes, segment_original_data,
     *   segments (batched)
     *
     * Phase 3 — File/project-scoped + root records:
     *   files_parts, files, file_references, file_metadata,
     *   context_groups, project_metadata, projects, jobs
     *
     * @param int $idProject
     * @param int $batchSize Max rows per batched DELETE (must be >= 1)
     *
     * @throws \InvalidArgumentException if $batchSize < 1
     * @throws PDOException
     */
    public function deleteProject(int $idProject, int $batchSize = 200): void
    {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException("batchSize must be >= 1, got $batchSize");
        }

        $conn = $this->dbHandler->getConnection();

        // Fetch all jobs for the project
        $stmt = $conn->prepare("SELECT id, job_first_segment, job_last_segment FROM jobs WHERE id_project = :id_project");
        $stmt->execute(['id_project' => $idProject]);
        /** @var list<array{id: int, job_first_segment: int, job_last_segment: int}> $jobs */
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->deleteJobScopedData($conn, $jobs, $batchSize);
        $this->deleteSegmentScopedData($conn, $jobs, $batchSize);
        $this->deleteFileAndProjectScopedData($conn, $idProject, $jobs);
    }

    /**
     * Phase 1 — Delete all job-scoped child rows.
     *
     * Per job: comments, qa_chunk_reviews, segment_translation_events,
     * segment_translation_versions, segment_translations (batched),
     * files_job, job_metadata.
     *
     * @param PDO $conn
     * @param list<array{id: int, job_first_segment: int, job_last_segment: int}> $jobs
     * @param int $batchSize
     *
     * @throws PDOException
     */
    private function deleteJobScopedData(PDO $conn, array $jobs, int $batchSize): void
    {
        foreach ($jobs as $job) {
            $idJob        = (int) $job['id'];
            $firstSegment = (int) $job['job_first_segment'];
            $lastSegment  = (int) $job['job_last_segment'];

            $stmt = $conn->prepare("DELETE FROM comments WHERE id_job = :id_job");
            $stmt->execute(['id_job' => $idJob]);

            $stmt = $conn->prepare("DELETE FROM qa_chunk_reviews WHERE id_job = :id_job");
            $stmt->execute(['id_job' => $idJob]);

            $stmt = $conn->prepare("DELETE FROM segment_translation_events WHERE id_job = :id_job");
            $stmt->execute(['id_job' => $idJob]);

            $stmt = $conn->prepare("DELETE FROM segment_translation_versions WHERE id_job = :id_job");
            $stmt->execute(['id_job' => $idJob]);

            // segment_translations batched to avoid large deletion spikes
            $this->deleteInBatches(
                $conn,
                "DELETE FROM segment_translations WHERE id_job = :id_job AND id_segment BETWEEN :start AND :end",
                $firstSegment,
                $lastSegment,
                $batchSize,
                ['id_job' => $idJob]
            );

            $stmt = $conn->prepare("DELETE FROM files_job WHERE id_job = :id_job");
            $stmt->execute(['id_job' => $idJob]);

            $stmt = $conn->prepare("DELETE FROM job_metadata WHERE id_job = :id_job");
            $stmt->execute(['id_job' => $idJob]);
        }
    }

    /**
     * Phase 2 — Delete segment-scoped rows using each job's own segment range.
     *
     * Each job's job_first_segment/job_last_segment defines a contiguous range.
     * We iterate per-job to avoid spanning gaps between non-contiguous jobs
     * that could include segments from other projects.
     *
     * Tables: segment_metadata, segment_notes, segment_original_data,
     * segments. All batched via deleteInBatches().
     *
     * @param PDO $conn
     * @param list<array{id: int, job_first_segment: int, job_last_segment: int}> $jobs
     * @param int $batchSize
     *
     * @throws PDOException
     */
    private function deleteSegmentScopedData(PDO $conn, array $jobs, int $batchSize): void
    {
        foreach ($jobs as $job) {
            $firstSegment = (int) $job['job_first_segment'];
            $lastSegment  = (int) $job['job_last_segment'];

            $this->deleteInBatches(
                $conn,
                "DELETE FROM segment_metadata WHERE id_segment BETWEEN :start AND :end",
                $firstSegment,
                $lastSegment,
                $batchSize
            );

            $this->deleteInBatches(
                $conn,
                "DELETE FROM segment_notes WHERE id_segment BETWEEN :start AND :end",
                $firstSegment,
                $lastSegment,
                $batchSize
            );

            $this->deleteInBatches(
                $conn,
                "DELETE FROM segment_original_data WHERE id_segment BETWEEN :start AND :end",
                $firstSegment,
                $lastSegment,
                $batchSize
            );

            $this->deleteInBatches(
                $conn,
                "DELETE FROM segments WHERE id BETWEEN :start AND :end",
                $firstSegment,
                $lastSegment,
                $batchSize
            );
        }
    }

    /**
     * Phase 3 — Delete file-scoped data, project metadata, and root records.
     *
     * Tables: files_parts, files, file_references, file_metadata,
     * context_groups, project_metadata, projects, jobs.
     *
     * @param PDO $conn
     * @param int $idProject
     * @param list<array{id: int, job_first_segment: int, job_last_segment: int}> $jobs
     *
     * @throws PDOException
     */
    private function deleteFileAndProjectScopedData(PDO $conn, int $idProject, array $jobs): void
    {
        // --- File-scoped deletions ---
        $stmt = $conn->prepare(
            "DELETE FROM files_parts WHERE id_file IN (
                SELECT id FROM files WHERE id_project = :id_project
            )"
        );
        $stmt->execute(['id_project' => $idProject]);

        $stmt = $conn->prepare("DELETE FROM files WHERE id_project = :id_project");
        $stmt->execute(['id_project' => $idProject]);

        $stmt = $conn->prepare("DELETE FROM file_references WHERE id_project = :id_project");
        $stmt->execute(['id_project' => $idProject]);

        $stmt = $conn->prepare("DELETE FROM file_metadata WHERE id_project = :id_project");
        $stmt->execute(['id_project' => $idProject]);

        $stmt = $conn->prepare("DELETE FROM context_groups WHERE id_project = :id_project");
        $stmt->execute(['id_project' => $idProject]);

        // --- Project-scoped metadata ---
        $stmt = $conn->prepare("DELETE FROM project_metadata WHERE id_project = :id_project");
        $stmt->execute(['id_project' => $idProject]);

        // --- Root records ---
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = :id_project");
        $stmt->execute(['id_project' => $idProject]);

        foreach ($jobs as $job) {
            $stmt = $conn->prepare("DELETE FROM jobs WHERE id = :id_job");
            $stmt->execute(['id_job' => (int) $job['id']]);
        }
    }

    /** @var int Pause between batches in microseconds (default 300ms — matches production cleanup script). */
    public static int $batchSleepMicroseconds = 300000;

    /**
     * Executes DELETE statements in batches over a segment ID range to avoid
     * large single-statement deletions that spike replication lag.
     *
     * @param PDO $conn
     * @param string $sql DELETE statement with :start and :end placeholders
     * @param int $firstSegment
     * @param int $lastSegment
     * @param int $batchSize
     * @param array<string, mixed> $extraParams Additional bound parameters (e.g. ['id_job' => 10])
     *
     * @throws PDOException
     */
    private function deleteInBatches(
        PDO   $conn,
        string $sql,
        int    $firstSegment,
        int    $lastSegment,
        int    $batchSize,
        array  $extraParams = []
    ): void {
        $currentStart = $firstSegment;

        while ($currentStart <= $lastSegment) {
            $currentEnd = min($currentStart + $batchSize - 1, $lastSegment);

            $params = array_merge($extraParams, [
                'start' => $currentStart,
                'end'   => $currentEnd,
            ]);

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $currentStart = $currentEnd + 1;

            if ($currentStart <= $lastSegment && self::$batchSleepMicroseconds > 0) {
                usleep(self::$batchSleepMicroseconds);
            }
        }
    }

}
