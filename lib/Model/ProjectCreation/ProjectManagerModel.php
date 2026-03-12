<?php

/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 13/06/19
 * Time: 12.35
 *
 */

namespace Model\ProjectCreation;

use ArrayObject;
use Exception;
use Model\Concerns\LogsMessages;
use Model\DataAccess\IDatabase;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDOException;
use PDOStatement;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Utils\Logger\MatecatLogger;
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
    public function createProjectRecord(ProjectCreationConfig $config, ?int $idTeam, string $status, ?int $idAssignee): ProjectStruct
    {
        $data = [];
        $data['id'] = $config->idProject;
        $data['id_customer'] = $config->idCustomer;
        $data['id_team'] = $idTeam;
        $data['name'] = $config->projectName;
        $data['create_date'] = $config->createDate;
        $data['status_analysis'] = $status;
        $data['password'] = $config->ppassword;
        $data['pretranslate_100'] = $config->pretranslate100;
        $data['remote_ip_address'] = empty($config->userIp) ? 'UNKNOWN' : $config->userIp;
        $data['id_assignee'] = $idAssignee;
        $data['instance_id'] = $config->instanceId ?: null;
        $data['due_date'] = $config->dueDate;

        $this->dbHandler->begin();
        $projectId = $this->dbHandler->insert('projects', $data);
        $project = ProjectDao::findById($projectId);
        $this->dbHandler->commit();

        if ($project === null) {
            throw new RuntimeException("Failed to retrieve project after insert (id: $projectId)");
        }

        return $project;
    }

    /**
     * @throws Exception
     */
    public function insertFile(int $idProject, string $sourceLanguage, string $file_name, string $mime_type, string $fileDateSha1Path): string
    {
        $data = [];
        $data['id_project'] = $idProject;
        $data['filename'] = $file_name;
        $data['source_language'] = $sourceLanguage;
        $data['mime_type'] = $mime_type;
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
     * @param-out list<list<array<string, mixed>>> $query_translations_values
     * @throws PDOException
     */
    public function insertPreTranslations(array &$query_translations_values): void
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

        $tuple_marks = "( ?, ?, ?, ?, ?, ?, NOW(), 'SKIPPED', ?, ?, ?, ?, ?, ?, ?, ? )";

        $this->log("Pre-Translations: Total Rows to insert: " . count($query_translations_values));

        //split the query in to chunks if there are too many segments
        $query_translations_values = array_chunk($query_translations_values, 100);

        $this->log("Pre-Translations: Total Queries to execute: " . count($query_translations_values));

        foreach ($query_translations_values as $i => $chunk) {
            try {
                $query = $baseQuery . rtrim(str_repeat($tuple_marks . ", ", count($chunk)), ", ");
                $stmt = $this->dbHandler->getConnection()->prepare($query);
                $stmt->execute(iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($chunk)), false));

                $this->log("Pre-Translations: Executed Query " . ($i + 1));
            } catch (PDOException $e) {
                $this->log("Segment import - DB Error: " . $e->getMessage() . " - \n", $e);
                throw new PDOException("Translations Segment import - DB Error: " . $e->getMessage() . " - " . var_export($chunk, true), -2);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>>|ArrayObject<int, array<string, mixed>> $notes
     *
     * @throws Exception
     */
    public function bulkInsertSegmentNotes(array|ArrayObject $notes): void
    {
        $template = " INSERT INTO segment_notes ( id_segment, internal_id, note, json ) VALUES ";

        $insert_values = [];
        $chunk_size = 30;

        foreach ($notes as $internal_id => $v) {
            $attributes = $v['from'];
            $entries = $v['entries'];
            $segments = $v['segment_ids'];

            $json_entries = $v['json'];
            $json_segment_ids = $v['json_segment_ids'];

            foreach ($segments as $id_segment) {
                foreach ($entries as $index => $note) {
                    // NOTE
                    // we need to strip tags from $note
                    // to prevent possible xss attacks
                    // from the UI

                    if (isset($attributes['entries'][$index])) {
                        $metaKey = Utils::stripTagsPreservingHrefs(html_entity_decode($attributes['entries'][$index]));

                        // check for metaKey is `notes`
                        if (!self::isAMetadata($metaKey)) {
                            $insert_values[] = [$id_segment, $internal_id, Utils::stripTagsPreservingHrefs(html_entity_decode($note)), null];
                        }
                    } else {
                        $insert_values[] = [$id_segment, $internal_id, Utils::stripTagsPreservingHrefs(html_entity_decode($note)), null];
                    }
                }
            }

            foreach ($json_segment_ids as $id_segment) {
                foreach ($json_entries as $index => $json) {
                    if (isset($attributes['json'][$index])) {
                        $metaKey = $attributes['json'][$index];

                        if (!self::isAMetadata($metaKey)) {
                            $insert_values[] = [$id_segment, $internal_id, null, $json];
                        }
                    } else {
                        $insert_values[] = [$id_segment, $internal_id, null, $json];
                    }
                }
            }
        }

        $chunked = array_chunk($insert_values, $chunk_size);
        $this->log("Notes: Total Rows to insert: " . count($chunked));

        $conn = $this->dbHandler->getConnection();
        $stmt = null;
        $flattened_values = [];

        try {
            foreach ($chunked as $i => $chunk) {
                $values_sql_array = array_fill(0, count($chunk), " ( ?, ?, ?, ? ) ");
                $stmt = $conn->prepare($template . implode(', ', $values_sql_array));
                $flattened_values = array_reduce($chunk, 'array_merge', []);
                $stmt->execute($flattened_values);
                $this->log("Notes: Executed Query " . ($i + 1));
            }
        } catch (Exception $e) {
            $this->log("Notes import - DB Error: " . $e->getMessage());
            $this->log("Notes import - Statement: " . ($stmt instanceof PDOStatement ? $stmt->queryString : 'N/A'));
            $this->log("Notes Chunk Dump: " . var_export($chunk, true));
            $this->log("Notes Flattened Values Dump: " . var_export($flattened_values, true));
            throw new Exception("Notes import - DB Error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<int, array<string, mixed>>|ArrayObject<int, array<string, mixed>> $notes
     *
     * @throws Exception
     */
    public function bulkInsertSegmentMetaDataFromAttributes(array|ArrayObject $notes): void
    {
        $template = " INSERT INTO segment_metadata ( id_segment, meta_key, meta_value ) VALUES ";

        $insert_values = [];
        $chunk_size = 30;

        foreach ($notes as $v) {
            $attributes = $v['from'];
            $entries = $v['entries'];
            $segments = $v['segment_ids'];

            $json_entries = $v['json'];
            $json_segment_ids = $v['json_segment_ids'];

            foreach ($segments as $id_segment) {
                foreach ($entries as $index => $note) {
                    if (isset($attributes['entries'][$index])) {
                        $metaKey = Utils::stripTagsPreservingHrefs(html_entity_decode($attributes['entries'][$index]));
                        $metaValue = Utils::stripTagsPreservingHrefs(html_entity_decode($note));

                        if (self::isAMetadata($metaKey)) {
                            $insert_values[] = [$id_segment, $metaKey, $metaValue];
                        }
                    }
                }
            }

            foreach ($json_segment_ids as $id_segment) {
                foreach ($json_entries as $index => $json) {
                    if (isset($attributes['json'][$index])) {
                        $metaKey = $attributes['json'][$index];
                        $metaValue = $json;

                        if (self::isAMetadata($metaKey)) {
                            $insert_values[] = [$id_segment, $metaKey, $metaValue];
                        }
                    }
                }
            }
        }

        $chunked = array_chunk($insert_values, $chunk_size);
        $this->log("Notes attributes: Total Rows to insert: " . count($chunked));

        $conn = $this->dbHandler->getConnection();
        $stmt = null;
        $flattened_values = [];

        try {
            foreach ($chunked as $i => $chunk) {
                $values_sql_array = array_fill(0, count($chunk), " ( ?, ?, ? ) ");
                $stmt = $conn->prepare($template . implode(', ', $values_sql_array));
                $flattened_values = array_reduce($chunk, 'array_merge', []);
                $stmt->execute($flattened_values);
                $this->log("Notes attributes: Executed Query " . ($i + 1));
            }
        } catch (Exception $e) {
            $this->log("Notes attributes import - DB Error: " . $e->getMessage());
            $this->log("Notes attributes import - Statement: " . ($stmt instanceof PDOStatement ? $stmt->queryString : 'N/A'));
            $this->log("Notes attributes Chunk Dump: " . var_export($chunk, true));
            $this->log("Notes attributes Flattened Values Dump: " . var_export($flattened_values, true));
            throw new Exception("Notes attributes import - DB Error: " . $e->getMessage(), 0, $e);
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
     * @param array<int, array<string, mixed>> $contextGroups
     *
     * @throws Exception
     */
    public function bulkInsertContextsGroups(int $idProject, array $contextGroups): void
    {
        $template = " INSERT INTO context_groups ( id_project, id_segment, context_json ) VALUES ";

        $insert_values = [];
        $chunk_size = 30;

        foreach ($contextGroups as $v) {
            $context_json = json_encode($v['context_json']);
            $segments = $v['context_json_segment_ids'];

            foreach ($segments as $id_segment) {
                $insert_values[] = [$idProject, $id_segment, $context_json];
            }
        }

        $chunked = array_chunk($insert_values, $chunk_size);
        $this->log("Notes: Total Rows to insert: " . count($chunked));

        $conn = $this->dbHandler->getConnection();
        $stmt = null;
        $flattened_values = [];

        try {
            foreach ($chunked as $i => $chunk) {
                $values_sql_array = array_fill(0, count($chunk), " ( ?, ?, ? ) ");
                $stmt = $conn->prepare($template . implode(', ', $values_sql_array));
                $flattened_values = array_reduce($chunk, 'array_merge', []);
                $stmt->execute($flattened_values);
                $this->log("Notes: Executed Query " . ($i + 1));
            }
        } catch (Exception $e) {
            $this->log("Trans-Unit Context Groups import - DB Error: " . $e->getMessage());
            $this->log("Trans-Unit Context Groups import - Statement: " . ($stmt instanceof PDOStatement ? $stmt->queryString : 'N/A'));
            $this->log("Trans-Unit Context Groups Chunk Dump: " . var_export($chunk, true));
            $this->log("Trans-Unit Context Groups Flattened Values Dump: " . var_export($flattened_values, true));
            throw new Exception("Notes import - DB Error: " . $e->getMessage(), 0, $e);
        }
    }

}
