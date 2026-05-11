<?php

use Model\DataAccess\Database;
use Model\Translations\SegmentTranslationDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class SetAnalysisValueIdempotencyTest extends AbstractTest
{
    private const TEST_SEGMENT_ID = 999999;
    private const TEST_JOB_ID = 999999;

    private Database $database;

    public function setUp(): void
    {
        parent::setUp();

        $this->database = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        $this->cleanupFixture();

        $stmt = $this->database->getConnection()->prepare(
            "INSERT INTO segment_translations
                (id_segment, id_job, segment_hash, time_to_edit, tm_analysis_status)
             VALUES
                (:id_segment, :id_job, :segment_hash, :time_to_edit, :tm_analysis_status)"
        );

        $stmt->execute([
            'id_segment' => self::TEST_SEGMENT_ID,
            'id_job' => self::TEST_JOB_ID,
            'segment_hash' => 'test_hash_idempotency',
            'time_to_edit' => 0,
            'tm_analysis_status' => 'DONE',
        ]);
    }

    public function tearDown(): void
    {
        $this->cleanupFixture();
        parent::tearDown();
    }

    #[Test]
    public function test_setAnalysisValue_returns_zero_when_segment_is_already_done(): void
    {
        $affected = SegmentTranslationDao::setAnalysisValue([
            'id_segment' => self::TEST_SEGMENT_ID,
            'id_job' => self::TEST_JOB_ID,
            'time_to_edit' => 123,
            'tm_analysis_status' => 'DONE',
        ]);

        $this->assertSame(0, $affected);
    }

    private function cleanupFixture(): void
    {
        $stmt = $this->database->getConnection()->prepare(
            'DELETE FROM segment_translations WHERE id_segment = :id_segment AND id_job = :id_job'
        );

        $stmt->execute([
            'id_segment' => self::TEST_SEGMENT_ID,
            'id_job'     => self::TEST_JOB_ID,
        ]);
    }
}
