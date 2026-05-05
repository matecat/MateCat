<?php

use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Plugins\Features\SegmentFilter\Model\FilterDefinition;
use Plugins\Features\SegmentFilter\Model\SegmentFilterDao;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

/**
 * @group regression
 * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao
 */
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

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsBySimpleFilter
     */
    public function test_findSegmentIdsBySimpleFilter_excludes_segments_with_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition(['status' => 'TRANSLATED']);
        $results = SegmentFilterDao::findSegmentIdsBySimpleFilter($this->chunk, $filter);

        $ids = array_map(fn($r) => (int)$r->id, $results);

        // Segments 1-5 are TRANSLATED, but 8-10 have show_in_cattool=0
        // So only segments 1-5 should be returned (all have show_in_cattool=1)
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }

        $this->assertNotEmpty($ids);
        $this->assertCount(5, $ids);
    }

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsBySimpleFilter
     */
    public function test_findSegmentIdsBySimpleFilter_NEW_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition(['status' => 'NEW']);
        $results = SegmentFilterDao::findSegmentIdsBySimpleFilter($this->chunk, $filter);

        $ids = array_map(fn($r) => (int)$r->id, $results);

        // Segments 6-10 are NEW, but 8-10 have show_in_cattool=0
        // Only segments 6,7 should be returned
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }

        $this->assertCount(2, $ids);
    }

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsForSample
     */
    public function test_findSegmentIdsForSample_matchType_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => [
                'type' => 'mt',
                'size' => 0,
            ]
        ]);

        $results = SegmentFilterDao::findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        // All segments have match_type = MT, but 8-10 have show_in_cattool=0
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }

        $this->assertCount(7, $ids);
    }

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsForSample
     */
    public function test_findSegmentIdsForSample_todo_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => [
                'type' => 'todo',
                'size' => 0,
            ]
        ]);

        $results = SegmentFilterDao::findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        // NEW segments are 6-10; segments 8-10 have show_in_cattool=0
        // Only 6,7 should be returned
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }

        $this->assertCount(2, $ids);
    }

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsForSample
     */
    public function test_findSegmentIdsForSample_editDistance_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => [
                'type' => 'edit_distance_high_to_low',
                'size' => 100,
            ]
        ]);

        $results = SegmentFilterDao::findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }
    }

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsForSample
     */
    public function test_findSegmentIdsForSample_segmentLength_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => [
                'type' => 'segment_length_high_to_low',
                'size' => 100,
            ]
        ]);

        $results = SegmentFilterDao::findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        $this->assertNotEmpty($ids);
        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }
    }

    /**
     * @group regression
     * @covers \Plugins\Features\SegmentFilter\Model\SegmentFilterDao::findSegmentIdsForSample
     */
    public function test_findSegmentIdsForSample_unlocked_excludes_show_in_cattool_0(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => [
                'type' => 'unlocked',
                'size' => 0,
            ]
        ]);

        $results = SegmentFilterDao::findSegmentIdsForSample($this->chunk, $filter);
        $ids = array_map(fn($r) => (int)$r->id, $results);

        foreach ($ids as $id) {
            $this->assertLessThanOrEqual($this->maxVisibleSegmentId(), $id, "Segment with show_in_cattool=0 should not be included");
        }
    }
}

