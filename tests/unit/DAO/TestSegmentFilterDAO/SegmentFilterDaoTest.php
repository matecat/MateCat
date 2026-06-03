<?php

use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Group;
use Plugins\Features\SegmentFilter\Model\FilterDefinition;
use Plugins\Features\SegmentFilter\Model\SegmentFilterDao;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

/**
 * @group regression
 * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao
 */
#[Group('PersistenceNeeded')]
class SegmentFilterDaoTest extends AbstractTest
{
    protected Database $database_instance;
    protected JobStruct $chunk;
    protected int $jobId;
    protected int $baseSegmentId;

    public function setUp(): void
    {
        parent::setUp();

        $this->database_instance = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $conn = $this->database_instance->getConnection();

        // Use a unique base to avoid collisions with parallel tests
        $this->baseSegmentId = (int)(microtime(true) * 1000) % 2000000000;

        // Create a job
        $conn->query(
            "INSERT INTO jobs
                (password, id_project, job_first_segment, job_last_segment, id_translator, tm_keys,
                job_type, source, target, total_time_to_edit, only_private_tm, last_opened_segment, id_tms, id_mt_engine,
                create_date, last_update, disabled, owner, status_owner, status_translator, status, completed, new_words,
                draft_words, translated_words, approved_words, rejected_words, subject, payable_rates, avg_post_editing_effort, total_raw_wc,
                approved2_words, new_raw_words, draft_raw_words, translated_raw_words, approved_raw_words, approved2_raw_words, rejected_raw_words
                ) VALUES (
                    'test_pass', '99999', '{$this->baseSegmentId}', '" . ($this->baseSegmentId + 10) . "', '', '[]',
                    NULL, 'en-GB', 'it-IT', '0', '0', NULL, '1', '1', NOW(), NOW(), '0',
                    'test@test.com', 'active', NULL, 'active', false, '0', '0', '0', '0', '0', 'general',
                    '{}', '0', '0', 0,0,0,0,0,0,0
                )"
        );
        $this->jobId = (int)$conn->lastInsertId();

        // Create segments: some with show_in_cattool = 1, some with show_in_cattool = 0
        for ($i = 1; $i <= 10; $i++) {
            $segId = $this->baseSegmentId + $i;
            $showInCattool = ($i <= 7) ? 1 : 0; // segments 8,9,10 should NOT appear
            $conn->query(
                "INSERT INTO segments (id, id_file, id_file_part, segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool, xliff_mrk_ext_prec_tags, xliff_mrk_ext_succ_tags)
                 VALUES ($segId, 1, NULL, 'Test segment $i', MD5('Test segment $segId'), 3, NULL, NULL, NULL, $showInCattool, NULL, NULL)"
            );
        }

        // Create segment_translations for the job
        for ($i = 1; $i <= 10; $i++) {
            $segId = $this->baseSegmentId + $i;
            $status = ($i <= 5) ? 'TRANSLATED' : 'NEW';
            $conn->query(
                "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, translation, translation_date, match_type, locked, version_number, edit_distance)
                 VALUES ($segId, {$this->jobId}, MD5('Test segment $segId'), '$status', 'Translation $i', NOW(), 'MT', 0, 0, $i)"
            );
        }

        // Build the chunk
        $this->chunk = new JobStruct();
        $this->chunk->id = $this->jobId;
        $this->chunk->password = 'test_pass';
        $this->chunk->job_first_segment = $this->baseSegmentId + 1;
        $this->chunk->job_last_segment = $this->baseSegmentId + 10;
    }

    public function tearDown(): void
    {
        $conn = $this->database_instance->getConnection();
        $conn->query("DELETE FROM segment_translations WHERE id_job = {$this->jobId}");
        $conn->query("DELETE FROM jobs WHERE id = {$this->jobId}");
        $firstSeg = $this->baseSegmentId + 1;
        $lastSeg = $this->baseSegmentId + 10;
        $conn->query("DELETE FROM segments WHERE id BETWEEN $firstSeg AND $lastSeg");

        parent::tearDown();
    }

    /**
     * Helper to get the max allowed segment ID (those with show_in_cattool = 1).
     */
    private function maxVisibleSegmentId(): int
    {
        return $this->baseSegmentId + 7;
    }

    // --- Instance method tests ---

    public function test_instance_findSegmentIdsBySimpleFilter_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition(['status' => 'TRANSLATED']);
        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsBySimpleFilter($this->chunk, $filter);

        $ids = array_map(fn($r) => (int)$r->id, $results);

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }

        $this->assertNotEmpty($ids);
        $this->assertCount(5, $ids);
    }

    public function test_instance_findSegmentIdsBySimpleFilter_NEW_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition(['status' => 'NEW']);
        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsBySimpleFilter($this->chunk, $filter);

        $ids = array_map(fn($r) => (int)$r->id, $results);

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }

        $this->assertCount(2, $ids);
    }

    public function test_instance_findSegmentIdsForSample_matchType(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'mt', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }

        $this->assertCount(7, $ids);
    }

    public function test_instance_findSegmentIdsForSample_todo(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'todo', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }

        $this->assertCount(2, $ids);
    }

    public function test_instance_findSegmentIdsForSample_editDistance(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'edit_distance_high_to_low', 'size' => 100]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }
    }

    public function test_instance_findSegmentIdsForSample_segmentLength(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'segment_length_high_to_low', 'size' => 100]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }
    }

    public function test_instance_findSegmentIdsForSample_unlocked(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'unlocked', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id);
        }
    }

    // --- SQL builder unit tests ---

    public function test_instance_getSqlForUnlocked_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => ' AND st.status = :status ', 'data' => ['status' => 'NEW']];

        $sql = $dao->getSqlForUnlocked($where);

        $this->assertStringContainsString('locked = 0', $sql);
        $this->assertStringContainsString('AND st.status = :status', $sql);
    }

    public function test_instance_getSqlForIce_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForIce($where);

        $this->assertStringContainsString("match_type = 'ICE'", $sql);
        $this->assertStringContainsString('version_number = 0', $sql);
    }

    public function test_instance_getSqlForModifiedIce_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForModifiedIce($where);

        $this->assertStringContainsString("match_type = 'ICE'", $sql);
        $this->assertStringContainsString('version_number > 0', $sql);
    }

    public function test_instance_getSqlForToDo_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForToDo($where);
        $this->assertStringContainsString('status_new', $sql);
        $this->assertStringContainsString('status_draft', $sql);
    }

    public function test_instance_getSqlForToDo_includes_review_condition(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForToDo($where, true);
        $this->assertStringContainsString('status_translated', $sql);
    }

    public function test_instance_getSqlForToDo_includes_second_pass_review_condition(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForToDo($where, false, true);
        $this->assertStringContainsString('status_translated', $sql);
        $this->assertStringContainsString('status_approved', $sql);
    }

    public function test_instance_getSqlForMatchType_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForMatchType($where);
        $this->assertStringContainsString('match_type = :match_type', $sql);
    }

    public function test_instance_getSqlForMatches_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForMatches($where);
        $this->assertStringContainsString('match_type_100_public', $sql);
        $this->assertStringContainsString('match_type_100', $sql);
    }

    public function test_instance_getSqlForRepetition_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForRepetition($where);
        $this->assertStringContainsString('segment_hash', $sql);
        $this->assertStringContainsString('HAVING COUNT', $sql);
    }

    public function test_instance_getSqlForEditDistance_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $limit = ['limit' => 10, 'count' => 100, 'sample_size' => 10];
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForEditDistance($limit, $where, 'high_to_low');
        $this->assertStringContainsString('edit_distance DESC', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);

        $sql = $dao->getSqlForEditDistance($limit, $where, 'low_to_high');
        $this->assertStringContainsString('edit_distance ASC', $sql);
    }

    public function test_instance_getSqlForSegmentLength_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $limit = ['limit' => 5, 'count' => 50, 'sample_size' => 10];
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForSegmentLength($limit, $where, 'high_to_low');
        $this->assertStringContainsString('CHAR_LENGTH(s.segment) DESC', $sql);
        $this->assertStringContainsString('LIMIT 5', $sql);
    }

    public function test_instance_getSqlForRegularIntervals_returns_valid_sql(): void
    {
        $dao = new SegmentFilterDao();
        $limit = ['limit' => 10, 'count' => 100, 'sample_size' => 10];
        $where = ['sql' => '', 'data' => []];

        $sql = $dao->getSqlForRegularIntervals($limit, $where);
        $this->assertStringContainsString('rowNumber', $sql);
        $this->assertStringContainsString('@curRow', $sql);
    }

    public function test_instance_findSegmentIdsForSample_ice_returns_empty_when_no_ice(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'ice', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $this->assertSame([], $results);
    }

    public function test_instance_findSegmentIdsForSample_modified_ice_returns_empty_when_no_modified_ice(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'modified_ice', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $this->assertSame([], $results);
    }

    public function test_instance_findSegmentIdsForSample_matches_returns_empty_when_no_100_matches(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'matches', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $this->assertSame([], $results);
    }

    public function test_instance_findSegmentIdsForSample_fuzzies_return_empty_when_no_fuzzies(): void
    {
        $dao = new SegmentFilterDao();

        foreach (['fuzzies_50_74', 'fuzzies_75_84', 'fuzzies_85_94', 'fuzzies_95_99'] as $type) {
            $filter = new FilterDefinition([
                'status' => '',
                'sample' => ['type' => $type, 'size' => 0]
            ]);

            $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
            $this->assertSame([], $results, "Expected empty for $type since fixtures have match_type=MT");
        }
    }

    public function test_instance_findSegmentIdsForSample_edit_distance_low_to_high(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'edit_distance_low_to_high', 'size' => 100]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $this->assertNotEmpty($results);
        foreach ($results as $r) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), (int)$r->id);
        }
    }

    public function test_instance_findSegmentIdsForSample_segment_length_low_to_high(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'segment_length_low_to_high', 'size' => 100]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $this->assertNotEmpty($results);
        foreach ($results as $r) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), (int)$r->id);
        }
    }

    public function test_instance_findSegmentIdsForSample_regular_intervals(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'regular_intervals', 'size' => 50]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $this->assertNotEmpty($results);
        foreach ($results as $r) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), (int)$r->id);
        }
    }

    public function test_instance_findSegmentIdsForSample_with_status_filter(): void
    {
        $filter = new FilterDefinition([
            'status' => 'TRANSLATED',
            'sample' => ['type' => 'mt', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();
        $results = $dao->findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        $this->assertCount(5, $ids);
    }

    public function test_instance_findSegmentIdsForSample_invalid_type_throws(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'invalid_type', 'size' => 0]
        ]);

        $dao = new SegmentFilterDao();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Sample type is not valid');
        $dao->findSegmentIdsForSample($this->chunk, $filter);
    }
}

