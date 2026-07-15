<?php

namespace Matecat\TestHelpers;

use Model\DataAccess\IDatabase;
use PDO;
use RuntimeException;

/**
 * Additive fixture builder for real-SQL DAO tests (plan dao-realsql-90.md, Wave 1 / T1).
 *
 * Builds rows directly against the live test schema (tests/inc/unittest_matecat_local.sql,
 * mirrored in the running unittest_matecat_local DB) so DAO JOINs resolve against real data
 * (C-3, M-1). Every fixture is created on the SAME per-test connection the DAO under test uses
 * (C-2) and is tracked for seed-safe DELETE cleanup (C-1, M-1, M-2).
 *
 * Two id strategies (M-2), derived from the live schema:
 *   - autoincrement: AUTO_INCREMENT tables (users, api_keys, projects, jobs, files, segments,
 *     segment_translation_versions, qa_entries, qa_entry_comments, qa_categories). The
 *     generated id is captured from lastInsertId() and tracked for cleanup. NO assertion is
 *     ever made on the absolute id value (M-3).
 *   - assignable: composite-PK tables with NO AUTO_INCREMENT (files_job, segment_translations).
 *     The id is assigned by the builder from a counter strictly >= 1_900_000_000, above the
 *     entire seeded id band (max seeded PK is users.uid = 1_886_591_200) so it can never
 *     collide with a seeded PK (M-2). Tracked by its assigned key for cleanup.
 *
 * Cleanup (C-1): an explicit id-list DELETE per tracked row, in reverse insertion order, on
 * the same connection. NEVER a wrapping transaction (many DAOs self-commit and nextSequence()
 * begins/commits unconditionally). NEVER deletes a seeded PK (M-1) - the builder only deletes
 * ids it inserted or rows a test explicitly registered via trackExisting().
 *
 * ADD-only: new builder methods may be added; existing signatures must not change.
 */
class TestFixtureBuilder
{
    /** Assignable-id floor: strictly above the seeded id band (max seeded PK 1_886_591_200). */
    public const int ASSIGNABLE_ID_FLOOR = 1_900_000_000;

    private IDatabase $db;

    /** Monotonic source for assignable ids, advanced per allocation within a test. */
    private int $assignableSeq = 0;

    /**
     * Tracked rows for cleanup, in insertion order. Each entry:
     *   ['table' => string, 'where' => array<string,int|string>]
     * Cleanup deletes them in REVERSE order so child rows go before parents.
     *
     * @var list<array{table:string,where:array<string,int|string>}>
     */
    private array $tracked = [];

    public function __construct(IDatabase $db)
    {
        $this->db = $db;
    }

    private function conn(): PDO
    {
        return $this->db->getConnection();
    }

    /** Next assignable id, strictly above the seeded band (M-2). */
    public function nextAssignableId(): int
    {
        return self::ASSIGNABLE_ID_FLOOR + (++$this->assignableSeq);
    }

    /**
     * INSERT into an AUTO_INCREMENT table, capture the generated id, track it for cleanup.
     *
     * @param array<string,int|string|null> $values
     * @return int generated id
     */
    private function insertAi(string $table, string $pk, array $values): int
    {
        $cols = array_keys($values);
        $place = array_map(static fn(string $c): string => ':' . $c, $cols);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', array_map(static fn(string $c): string => "`$c`", $cols)),
            implode(',', $place)
        );
        $stmt = $this->conn()->prepare($sql);
        $stmt->execute($values);
        $id = (int)$this->conn()->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException("insertAi($table) failed to obtain a generated id");
        }
        $this->tracked[] = ['table' => $table, 'where' => [$pk => $id]];

        return $id;
    }

    /**
     * INSERT into a table with assignable (no AUTO_INCREMENT) ids and track by composite key.
     *
     * @param array<string,int|string|null> $values
     * @param array<string,int|string>      $trackWhere identifying key for cleanup
     */
    private function insertAssignable(string $table, array $values, array $trackWhere): void
    {
        $cols = array_keys($values);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', array_map(static fn(string $c): string => "`$c`", $cols)),
            implode(',', array_map(static fn(string $c): string => ':' . $c, $cols))
        );
        $stmt = $this->conn()->prepare($sql);
        $stmt->execute($values);
        $this->tracked[] = ['table' => $table, 'where' => $trackWhere];
    }

    /**
     * Register an already-inserted row (e.g. one the DAO under test INSERTed itself) so the
     * whole-table residue gate returns to baseline. NEVER pass a seeded PK here (M-1).
     *
     * @param array<string,int|string> $where
     */
    public function trackExisting(string $table, array $where): void
    {
        $this->tracked[] = ['table' => $table, 'where' => $where];
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    // ---------------------------------------------------------------------------------------
    // users
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     * @return array{uid:int,email:string,confirmation_token:?string,first_name:string,last_name:string}
     */
    public function makeUser(array $overrides = []): array
    {
        $email = (string)($overrides['email'] ?? ('rsq_' . bin2hex(random_bytes(8)) . '@example.test'));
        $values = [
            'email'              => $email,
            'create_date'        => $this->now(),
            'first_name'         => (string)($overrides['first_name'] ?? 'RsqFirst'),
            'last_name'          => (string)($overrides['last_name'] ?? 'RsqLast'),
            'salt'               => (string)($overrides['salt'] ?? bin2hex(random_bytes(8))),
            'pass'               => (string)($overrides['pass'] ?? bin2hex(random_bytes(8))),
            'confirmation_token' => $overrides['confirmation_token'] ?? null,
            'oauth_access_token' => $overrides['oauth_access_token'] ?? null,
        ];
        $uid = $this->insertAi('users', 'uid', $values);

        return [
            'uid'                => $uid,
            'email'              => $email,
            'confirmation_token' => $values['confirmation_token'],
            'first_name'         => $values['first_name'],
            'last_name'          => $values['last_name'],
        ];
    }

    // ---------------------------------------------------------------------------------------
    // api_keys
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int,uid:int,api_key:string,api_secret:string,enabled:bool}
     */
    public function makeApiKey(int $uid, ?string $apiKey = null, bool $enabled = true): array
    {
        $apiKey = $apiKey ?? ('rsk_' . bin2hex(random_bytes(8)));
        $secret = 'sec_' . bin2hex(random_bytes(8));
        $id = $this->insertAi('api_keys', 'id', [
            'uid'         => $uid,
            'api_key'     => $apiKey,
            'api_secret'  => $secret,
            'create_date' => $this->now(),
            'last_update' => $this->now(),
            'enabled'     => $enabled ? 1 : 0,
        ]);

        return ['id' => $id, 'uid' => $uid, 'api_key' => $apiKey, 'api_secret' => $secret, 'enabled' => $enabled];
    }

    // ---------------------------------------------------------------------------------------
    // projects
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     * @return array{id:int}
     */
    public function makeProject(array $overrides = []): array
    {
        $values = [
            'id_customer' => (string)($overrides['id_customer'] ?? ('rsq_' . bin2hex(random_bytes(6)) . '@example.test')),
            'create_date' => $this->now(),
        ];
        if (isset($overrides['id_assignee'])) {
            $values['id_assignee'] = (int)$overrides['id_assignee'];
        }
        $id = $this->insertAi('projects', 'id', $values);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // jobs
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     * @return array{id:int,password:string,id_project:int,job_first_segment:int,job_last_segment:int}
     */
    public function makeJob(int $idProject, array $overrides = []): array
    {
        $password = (string)($overrides['password'] ?? substr(bin2hex(random_bytes(8)), 0, 12));
        $first = (int)($overrides['job_first_segment'] ?? 1);
        $last = (int)($overrides['job_last_segment'] ?? PHP_INT_MAX >> 8);
        $values = [
            'password'          => $password,
            'id_project'        => $idProject,
            'job_first_segment' => $first,
            'job_last_segment'  => $last,
            'tm_keys'           => (string)($overrides['tm_keys'] ?? '[]'),
            'create_date'       => $this->now(),
            'disabled'          => 0,
            'source'            => (string)($overrides['source'] ?? 'en-US'),
            'target'            => (string)($overrides['target'] ?? 'it-IT'),
        ];
        if (isset($overrides['owner'])) {
            $values['owner'] = (string)$overrides['owner'];
        }
        $id = $this->insertAi('jobs', 'id', $values);

        return [
            'id'                => $id,
            'password'          => $password,
            'id_project'        => $idProject,
            'job_first_segment' => $first,
            'job_last_segment'  => $last,
        ];
    }

    // ---------------------------------------------------------------------------------------
    // files
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int,id_project:int}
     */
    public function makeFile(int $idProject, string $sourceLanguage = 'en-US'): array
    {
        $id = $this->insertAi('files', 'id', [
            'id_project'      => $idProject,
            'source_language' => $sourceLanguage,
            'filename'        => 'rsq_' . bin2hex(random_bytes(4)) . '.txt',
        ]);

        return ['id' => $id, 'id_project' => $idProject];
    }

    // ---------------------------------------------------------------------------------------
    // files_job (assignable composite PK)
    // ---------------------------------------------------------------------------------------

    public function makeFilesJob(int $idJob, int $idFile): void
    {
        $this->insertAssignable(
            'files_job',
            ['id_job' => $idJob, 'id_file' => $idFile],
            ['id_job' => $idJob, 'id_file' => $idFile]
        );
    }

    // ---------------------------------------------------------------------------------------
    // segments
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int,id_file:int}
     */
    public function makeSegment(int $idFile, bool $showInCattool = true, string $segment = 'source text'): array
    {
        $id = $this->insertAi('segments', 'id', [
            'id_file'         => $idFile,
            'segment_hash'    => substr(bin2hex(random_bytes(16)), 0, 32),
            'segment'         => $segment,
            'show_in_cattool' => $showInCattool ? 1 : 0,
        ]);

        return ['id' => $id, 'id_file' => $idFile];
    }

    // ---------------------------------------------------------------------------------------
    // segment_translations (assignable composite PK id_segment,id_job)
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     */
    public function makeSegmentTranslation(int $idSegment, int $idJob, array $overrides = []): void
    {
        $values = [
            'id_segment'     => $idSegment,
            'id_job'         => $idJob,
            'segment_hash'   => substr(bin2hex(random_bytes(16)), 0, 32),
            'status'         => (string)($overrides['status'] ?? 'APPROVED'),
            'translation'    => (string)($overrides['translation'] ?? 'translated text'),
            'version_number' => (int)($overrides['version_number'] ?? 0),
            'edit_distance'  => (int)($overrides['edit_distance'] ?? 10),
            'time_to_edit'   => (int)($overrides['time_to_edit'] ?? 500),
        ];
        $this->insertAssignable(
            'segment_translations',
            $values,
            ['id_segment' => $idSegment, 'id_job' => $idJob]
        );
    }

    // ---------------------------------------------------------------------------------------
    // segment_translation_versions
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int}
     */
    public function makeSegmentTranslationVersion(int $idSegment, int $idJob, int $versionNumber = 0, string $translation = 'orig translation'): array
    {
        $id = $this->insertAi('segment_translation_versions', 'id', [
            'id_segment'     => $idSegment,
            'id_job'         => $idJob,
            'version_number' => $versionNumber,
            'translation'    => $translation,
        ]);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // qa_categories
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int}
     */
    public function makeQaCategory(string $label = 'RsqCategory', int $idModel = 1, string $options = '{}'): array
    {
        $id = $this->insertAi('qa_categories', 'id', [
            'id_model' => $idModel,
            'label'    => $label,
            'options'  => $options,
        ]);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // qa_entries
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     * @return array{id:int}
     */
    public function makeQaEntry(int $idSegment, int $idJob, int $idCategory, array $overrides = []): array
    {
        $values = [
            'id_segment'          => $idSegment,
            'id_job'              => $idJob,
            'id_category'         => $idCategory,
            'severity'            => (string)($overrides['severity'] ?? 'minor'),
            'translation_version' => (int)($overrides['translation_version'] ?? 0),
            'start_node'          => (int)($overrides['start_node'] ?? 0),
            'start_offset'        => (int)($overrides['start_offset'] ?? 0),
            'end_node'            => (int)($overrides['end_node'] ?? 0),
            'end_offset'          => (int)($overrides['end_offset'] ?? 0),
            'is_full_segment'     => (int)($overrides['is_full_segment'] ?? 1),
            'source_page'         => (int)($overrides['source_page'] ?? 2),
            'create_date'         => $this->now(),
        ];
        if (array_key_exists('comment', $overrides)) {
            $values['comment'] = $overrides['comment'];
        }
        if (array_key_exists('target_text', $overrides)) {
            $values['target_text'] = $overrides['target_text'];
        }
        $id = $this->insertAi('qa_entries', 'id', $values);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // qa_entry_comments
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int}
     */
    public function makeQaEntryComment(int $idQaEntry, ?int $uid = null, string $comment = 'rsq comment'): array
    {
        $id = $this->insertAi('qa_entry_comments', 'id', [
            'id_qa_entry' => $idQaEntry,
            'uid'         => $uid,
            'comment'     => $comment,
            'create_date' => $this->now(),
        ]);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // Deep multi-table topology for QualityReportDao (mirrors the join graph C-3).
    // ---------------------------------------------------------------------------------------

    /**
     * Build a complete, JOIN-resolvable chunk for QualityReportDao:
     *   project -> file -> job -> files_job -> segment -> segment_translation
     *           -> segment_translation_version(v0) -> qa_category -> qa_entry -> qa_entry_comment
     *
     * @return array{
     *   id_project:int,id_file:int,id_job:int,password:string,
     *   id_segment:int,id_category:int,id_qa_entry:int,id_qa_entry_comment:int
     * }
     */
    public function makeQualityReportChunk(int $uid): array
    {
        $project = $this->makeProject();
        $file = $this->makeFile($project['id']);
        // segment id must fall within [job_first_segment, job_last_segment]; create the segment
        // first, then bound the job to that segment id so all join predicates resolve.
        $segment = $this->makeSegment($file['id'], true);
        $job = $this->makeJob($project['id'], [
            'owner'             => $this->ownerEmailFor($uid),
            'job_first_segment' => $segment['id'],
            'job_last_segment'  => $segment['id'],
        ]);
        $this->makeFilesJob($job['id'], $file['id']);
        $this->makeSegmentTranslation($segment['id'], $job['id'], [
            'status'         => 'APPROVED',
            'version_number' => 1,
            'edit_distance'  => 20,
            'time_to_edit'   => 1000,
        ]);
        $this->makeSegmentTranslationVersion($segment['id'], $job['id'], 0, 'original v0');
        $category = $this->makeQaCategory('RsqCat', 1, '{}');
        $entry = $this->makeQaEntry($segment['id'], $job['id'], $category['id'], [
            'translation_version' => 1,
            'severity'            => 'minor',
            'source_page'         => 2,
            'comment'             => 'issue comment',
            'target_text'         => 'target',
        ]);
        $this->makeQaEntryComment($entry['id'], $uid, 'reviewer note');

        return [
            'id_project'          => $project['id'],
            'id_file'             => $file['id'],
            'id_job'              => $job['id'],
            'password'            => $job['password'],
            'id_segment'          => $segment['id'],
            'id_category'         => $category['id'],
            'id_qa_entry'         => $entry['id'],
            'id_qa_entry_comment' => 0,
        ];
    }

    /** Resolve a user's email for the jobs.owner = users.email join (getProjectOwner). */
    public function ownerEmailFor(int $uid): string
    {
        $stmt = $this->conn()->prepare('SELECT email FROM users WHERE uid = :uid');
        $stmt->execute(['uid' => $uid]);
        $email = $stmt->fetchColumn();

        return is_string($email) ? $email : '';
    }

    // ---------------------------------------------------------------------------------------
    // segments (richer variant) — additive, does not change makeSegment()'s signature
    // ---------------------------------------------------------------------------------------

    /**
     * Create a segment with word-count / file-part columns populated, for JobDao word-count
     * and file-part JOIN queries. Additive: leaves the existing makeSegment() untouched.
     *
     * @param array<string,int|string|null> $overrides
     * @return array{id:int,id_file:int,raw_word_count:int}
     */
    public function makeSegmentDetailed(int $idFile, array $overrides = []): array
    {
        $rawWordCount = (int)($overrides['raw_word_count'] ?? 10);
        $values = [
            'id_file'         => $idFile,
            'segment_hash'    => substr(bin2hex(random_bytes(16)), 0, 32),
            'segment'         => (string)($overrides['segment'] ?? 'source text'),
            'raw_word_count'  => $rawWordCount,
            'show_in_cattool' => ((int)($overrides['show_in_cattool'] ?? 1)) ? 1 : 0,
        ];
        if (array_key_exists('id_file_part', $overrides) && $overrides['id_file_part'] !== null) {
            $values['id_file_part'] = (int)$overrides['id_file_part'];
        }
        if (array_key_exists('internal_id', $overrides)) {
            $values['internal_id'] = $overrides['internal_id'];
        }
        $id = $this->insertAi('segments', 'id', $values);

        return ['id' => $id, 'id_file' => $idFile, 'raw_word_count' => $rawWordCount];
    }

    // ---------------------------------------------------------------------------------------
    // segment_translations (richer variant) — additive: a detailed assignable insert that
    // populates the word-count / suggestion / match columns JobDao queries read. The original
    // makeSegmentTranslation() is left untouched.
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     */
    public function makeSegmentTranslationDetailed(int $idSegment, int $idJob, array $overrides = []): void
    {
        $values = [
            'id_segment'          => $idSegment,
            'id_job'              => $idJob,
            'segment_hash'        => substr(bin2hex(random_bytes(16)), 0, 32),
            'status'              => (string)($overrides['status'] ?? 'APPROVED'),
            'translation'         => (string)($overrides['translation'] ?? 'translated text'),
            'suggestion'          => (string)($overrides['suggestion'] ?? 'a suggestion'),
            'match_type'          => (string)($overrides['match_type'] ?? 'MT'),
            'eq_word_count'       => (float)($overrides['eq_word_count'] ?? 8.0),
            'standard_word_count' => (float)($overrides['standard_word_count'] ?? 9.0),
            'version_number'      => (int)($overrides['version_number'] ?? 1),
            'edit_distance'       => (int)($overrides['edit_distance'] ?? 10),
            'time_to_edit'        => (int)($overrides['time_to_edit'] ?? 5000),
        ];
        $this->insertAssignable(
            'segment_translations',
            $values,
            ['id_segment' => $idSegment, 'id_job' => $idJob]
        );
    }

    // ---------------------------------------------------------------------------------------
    // segment_translation_events
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     * @return array{id:int}
     */
    public function makeSegmentTranslationEvent(int $idJob, int $idSegment, array $overrides = []): array
    {
        $id = $this->insertAi('segment_translation_events', 'id', [
            'id_job'         => $idJob,
            'id_segment'     => $idSegment,
            'uid'            => (int)($overrides['uid'] ?? 0),
            'version_number' => (int)($overrides['version_number'] ?? 1),
            'source_page'    => (int)($overrides['source_page'] ?? 1),
            'status'         => (string)($overrides['status'] ?? 'TRANSLATED'),
            'final_revision' => (int)($overrides['final_revision'] ?? 0),
            'time_to_edit'   => (int)($overrides['time_to_edit'] ?? 1500),
            'create_date'    => $this->now(),
        ]);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // files_parts
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int,id_file:int}
     */
    public function makeFilesPart(int $idFile, string $tagKey = 'rsq_key', string $tagValue = 'rsq_value'): array
    {
        $id = $this->insertAi('files_parts', 'id', [
            'id_file'   => $idFile,
            'tag_key'   => $tagKey,
            'tag_value' => $tagValue,
        ]);

        return ['id' => $id, 'id_file' => $idFile];
    }

    // ---------------------------------------------------------------------------------------
    // job_custom_payable_rates (assignable PK id_job)
    // ---------------------------------------------------------------------------------------

    public function makeJobCustomPayableRate(int $idJob, int $modelId = 1, string $modelName = 'RsqRate', int $modelVersion = 1): void
    {
        $this->insertAssignable(
            'job_custom_payable_rates',
            [
                'id_job'                          => $idJob,
                'custom_payable_rate_model_id'    => $modelId,
                'custom_payable_rate_model_name'  => $modelName,
                'custom_payable_rate_model_version' => $modelVersion,
            ],
            ['id_job' => $idJob]
        );
    }

    // ---------------------------------------------------------------------------------------
    // job_metadata
    // ---------------------------------------------------------------------------------------

    /**
     * @return array{id:int}
     */
    public function makeJobMetadata(int $idJob, string $password, string $key, string $value): array
    {
        $id = $this->insertAi('job_metadata', 'id', [
            'id_job'   => $idJob,
            'password' => $password,
            'key'      => $key,
            'value'    => $value,
        ]);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------------
    // projects (AUTO_INCREMENT) — detailed variant added for ProjectDao real-SQL tests (T5)
    // ---------------------------------------------------------------------------------------------

    /**
     * Like makeProject() but lets a test set arbitrary projects columns (name, password,
     * id_team, status_analysis, ...). Additive: the original makeProject() signature is unchanged.
     *
     * @param array<string,int|float|string|null> $overrides
     * @return array{id:int,password:string,name:string,id_customer:string}
     */
    public function makeProjectDetailed(array $overrides = []): array
    {
        $password = (string)($overrides['password'] ?? substr(bin2hex(random_bytes(8)), 0, 12));
        $name = (string)($overrides['name'] ?? ('rsq_proj_' . bin2hex(random_bytes(4))));
        $idCustomer = (string)($overrides['id_customer'] ?? ('rsq_' . bin2hex(random_bytes(6)) . '@example.test'));

        $values = [
            'id_customer'     => $idCustomer,
            'create_date'     => $this->now(),
            'password'        => $password,
            'name'            => $name,
            'status_analysis' => (string)($overrides['status_analysis'] ?? 'NEW'),
        ];
        foreach (['id_team', 'id_assignee', 'standard_analysis_wc'] as $col) {
            if (array_key_exists($col, $overrides)) {
                $values[$col] = $overrides[$col];
            }
        }
        $id = $this->insertAi('projects', 'id', $values);

        return ['id' => $id, 'password' => $password, 'name' => $name, 'id_customer' => $idCustomer];
    }

    // ---------------------------------------------------------------------------------------------
    // teams (AUTO_INCREMENT) — added for ProjectTemplateDao real-SQL tests (T5)
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array{id:int,created_by:int,type:string}
     */
    public function makeTeam(int $createdBy, string $type = 'personal'): array
    {
        $id = $this->insertAi('teams', 'id', [
            'name'       => 'rsq_' . bin2hex(random_bytes(4)),
            'created_by' => $createdBy,
            'created_at' => $this->now(),
            'type'       => $type,
        ]);

        return ['id' => $id, 'created_by' => $createdBy, 'type' => $type];
    }

    // ---------------------------------------------------------------------------------------------
    // teams_users (AUTO_INCREMENT) — added for ProjectTemplateDao real-SQL tests (T5)
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array{id:int,id_team:int,uid:int}
     */
    public function makeTeamUser(int $idTeam, int $uid, bool $isAdmin = true): array
    {
        $id = $this->insertAi('teams_users', 'id', [
            'id_team'  => $idTeam,
            'uid'      => $uid,
            'is_admin' => $isAdmin ? 1 : 0,
        ]);

        return ['id' => $id, 'id_team' => $idTeam, 'uid' => $uid];
    }

    // ---------------------------------------------------------------------------------------------
    // connected_services (AUTO_INCREMENT) — added for ProjectDao real-SQL tests (T5)
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array{id:int,uid:int,service:string}
     */
    public function makeConnectedService(int $uid, string $service = 'gdrive'): array
    {
        $id = $this->insertAi('connected_services', 'id', [
            'uid'                => $uid,
            'service'            => $service,
            'name'               => 'rsq_' . bin2hex(random_bytes(4)),
            'email'              => 'rsq_' . bin2hex(random_bytes(6)) . '@example.test',
            'oauth_access_token' => 'tok_' . bin2hex(random_bytes(8)),
            'created_at'         => $this->now(),
        ]);

        return ['id' => $id, 'uid' => $uid, 'service' => $service];
    }

    // ---------------------------------------------------------------------------------------------
    // remote_files (AUTO_INCREMENT) — added for ProjectDao real-SQL tests (T5)
    // ---------------------------------------------------------------------------------------------

    /**
     * @return array{id:int,id_file:int,id_job:int}
     */
    public function makeRemoteFile(int $idFile, int $idJob, ?int $connectedServiceId = null, bool $isOriginal = true): array
    {
        $values = [
            'id_file'      => $idFile,
            'id_job'       => $idJob,
            'remote_id'    => 'rsq_' . bin2hex(random_bytes(6)),
            'is_original'  => $isOriginal ? 1 : 0,
        ];
        if ($connectedServiceId !== null) {
            $values['connected_service_id'] = $connectedServiceId;
        }
        $id = $this->insertAi('remote_files', 'id', $values);

        return ['id' => $id, 'id_file' => $idFile, 'id_job' => $idJob];
    }

    // ---------------------------------------------------------------------------------------------
    // qa_chunk_reviews (AUTO_INCREMENT) — added for ProjectDao real-SQL tests (T5)
    // ---------------------------------------------------------------------------------------------

    /**
     * @param array<string,int|string|null> $overrides
     * @return array{id:int}
     */
    public function makeQaChunkReview(int $idProject, int $idJob, string $password, array $overrides = []): array
    {
        $id = $this->insertAi('qa_chunk_reviews', 'id', [
            'id_project'       => $idProject,
            'id_job'           => $idJob,
            'password'         => $password,
            'review_password'  => (string)($overrides['review_password'] ?? ('rev_' . bin2hex(random_bytes(4)))),
            'source_page'      => (int)($overrides['source_page'] ?? 2),
        ]);

        return ['id' => $id];
    }

    // ---------------------------------------------------------------------------------------
    // comments (AUTO_INCREMENT) — added for CommentDao real-SQL tests (T10). Additive: no
    // existing builder signature changes (plan ADD-only rule).
    // ---------------------------------------------------------------------------------------

    /**
     * Insert a row into `comments`, capture the generated id, track it for cleanup.
     *
     * Defaults mirror the seed topology so CommentDao JOIN/WHERE predicates resolve:
     * message_type defaults to 1 (TYPE_COMMENT), resolve_date NULL (open thread). Callers
     * link id_job/id_segment to fixtures they built (jobs/segments) so the residue gate stays
     * accurate across the comments + jobs + projects deps.
     *
     * @param array<string,int|string|null> $overrides
     * @return array{id:int,id_job:int,id_segment:int,uid:int|null,message_type:int,full_name:string}
     */
    public function makeComment(int $idJob, int $idSegment, array $overrides = []): array
    {
        $messageType = (int)($overrides['message_type'] ?? 1);
        $fullName = (string)($overrides['full_name'] ?? ('rsq_commenter_' . bin2hex(random_bytes(4))));
        $uid = array_key_exists('uid', $overrides) ? $overrides['uid'] : null;

        $values = [
            'id_job'       => $idJob,
            'id_segment'   => $idSegment,
            'create_date'  => (string)($overrides['create_date'] ?? $this->now()),
            'email'        => array_key_exists('email', $overrides) ? $overrides['email'] : ('rsq_' . bin2hex(random_bytes(5)) . '@example.test'),
            'full_name'    => $fullName,
            'uid'          => $uid === null ? null : (int)$uid,
            'resolve_date' => array_key_exists('resolve_date', $overrides) ? $overrides['resolve_date'] : null,
            'source_page'  => (int)($overrides['source_page'] ?? 1),
            'is_anonymous' => (int)($overrides['is_anonymous'] ?? 0),
            'message_type' => $messageType,
            'message'      => (string)($overrides['message'] ?? ('rsq message ' . bin2hex(random_bytes(4)))),
        ];
        $id = $this->insertAi('comments', 'id', $values);

        return [
            'id'           => $id,
            'id_job'       => $idJob,
            'id_segment'   => $idSegment,
            'uid'          => $uid === null ? null : (int)$uid,
            'message_type' => $messageType,
            'full_name'    => $fullName,
        ];
    }

    // ---------------------------------------------------------------------------------------
    // segments with an explicit raw_word_count (additive variant for WordCounterDao stats)
    // ---------------------------------------------------------------------------------------

    /**
     * Like makeSegment() but sets raw_word_count, which WordCounterDao::getStatsForJob() SUMs.
     * Additive (does not alter makeSegment's signature, plan ADD-only rule).
     *
     * @return array{id:int,id_file:int,raw_word_count:float}
     */
    public function makeSegmentWithWords(int $idFile, float $rawWordCount, bool $showInCattool = true, string $segment = 'source text'): array
    {
        $id = $this->insertAi('segments', 'id', [
            'id_file'         => $idFile,
            'segment_hash'    => substr(bin2hex(random_bytes(16)), 0, 32),
            'segment'         => $segment,
            'raw_word_count'  => $rawWordCount,
            'show_in_cattool' => $showInCattool ? 1 : 0,
        ]);

        return ['id' => $id, 'id_file' => $idFile, 'raw_word_count' => $rawWordCount];
    }

    // ---------------------------------------------------------------------------------------
    // segment_translations with an explicit eq_word_count (additive variant for stats)
    // ---------------------------------------------------------------------------------------

    /**
     * Like makeSegmentTranslation() but sets eq_word_count, which WordCounterDao SUMs.
     * Additive. status drives the per-bucket SUM (NEW/DRAFT/TRANSLATED/APPROVED/...).
     *
     * @param array<string,int|string|null> $overrides
     */
    public function makeSegmentTranslationWithWords(int $idSegment, int $idJob, float $eqWordCount, string $status = 'TRANSLATED', array $overrides = []): void
    {
        $values = [
            'id_segment'     => $idSegment,
            'id_job'         => $idJob,
            'segment_hash'   => substr(bin2hex(random_bytes(16)), 0, 32),
            'status'         => $status,
            'eq_word_count'  => $eqWordCount,
            'translation'    => (string)($overrides['translation'] ?? 'translated text'),
            'version_number' => (int)($overrides['version_number'] ?? 0),
            'edit_distance'  => (int)($overrides['edit_distance'] ?? 10),
            'time_to_edit'   => (int)($overrides['time_to_edit'] ?? 500),
        ];
        $this->insertAssignable(
            'segment_translations',
            $values,
            ['id_segment' => $idSegment, 'id_job' => $idJob]
        );
    }

    // ---------------------------------------------------------------------------------------
    // memory_keys (assignable composite PK uid,key_value; NO seed rows; NO AUTO_INCREMENT)
    // ---------------------------------------------------------------------------------------

    /**
     * Build a memory_keys row. uid defaults to an assignable id strictly above the seeded
     * band (M-2); key_value defaults to a random token unique within the test. Tracked by its
     * composite PK for seed-safe cleanup (M-1).
     *
     * @param array<string,int|string|null> $overrides
     * @return array{uid:int,key_value:string,key_name:string,key_tm:int,key_glos:int,deleted:int}
     */
    public function makeMemoryKey(array $overrides = []): array
    {
        $uid      = (int)($overrides['uid'] ?? $this->nextAssignableId());
        $keyValue = (string)($overrides['key_value'] ?? bin2hex(random_bytes(16)));
        $keyName  = (string)($overrides['key_name'] ?? ('rsq_key_' . bin2hex(random_bytes(4))));
        $keyTm    = (int)($overrides['key_tm'] ?? 1);
        $keyGlos  = (int)($overrides['key_glos'] ?? 1);
        $deleted  = (int)($overrides['deleted'] ?? 0);

        $this->insertAssignable(
            'memory_keys',
            [
                'uid'           => $uid,
                'key_value'     => $keyValue,
                'key_name'      => $keyName,
                'key_tm'        => $keyTm,
                'key_glos'      => $keyGlos,
                'creation_date' => $this->now(),
                'deleted'       => $deleted,
            ],
            ['uid' => $uid, 'key_value' => $keyValue]
        );

        return [
            'uid'       => $uid,
            'key_value' => $keyValue,
            'key_name'  => $keyName,
            'key_tm'    => $keyTm,
            'key_glos'  => $keyGlos,
            'deleted'   => $deleted,
        ];
    }

    // ---------------------------------------------------------------------------------------
    // segment_translations_splits (assignable composite PK id_segment,id_job) — Split DAO (T15)
    // ---------------------------------------------------------------------------------------

    /**
     * @param array<string,string|null> $overrides
     */
    public function makeSegmentTranslationsSplit(int $idSegment, int $idJob, array $overrides = []): void
    {
        $values = [
            'id_segment'           => $idSegment,
            'id_job'               => $idJob,
            'source_chunk_lengths' => (string)($overrides['source_chunk_lengths'] ?? '[5,7]'),
            'target_chunk_lengths' => (string)($overrides['target_chunk_lengths'] ?? '{"len":[5,7],"statuses":["DRAFT","DRAFT"]}'),
        ];
        $this->insertAssignable(
            'segment_translations_splits',
            $values,
            ['id_segment' => $idSegment, 'id_job' => $idJob]
        );
    }

    /**
     * Seed-safe id-list DELETE cleanup (C-1, M-1, M-2). Deletes only builder-inserted /
     * explicitly-tracked rows, in reverse insertion order, on the per-test connection. NO
     * wrapping transaction. After this runs the whole-table COUNT(*) returns to baseline for
     * every touched table (the residue gate in the trait asserts this).
     */
    public function cleanup(): void
    {
        foreach (array_reverse($this->tracked) as $row) {
            $where = $row['where'];
            $clauses = [];
            foreach (array_keys($where) as $col) {
                $clauses[] = "`$col` = :$col";
            }
            $sql = sprintf('DELETE FROM `%s` WHERE %s', $row['table'], implode(' AND ', $clauses));
            $stmt = $this->conn()->prepare($sql);
            $stmt->execute($where);
        }
        $this->tracked = [];
    }
}
