<?php

namespace Matecat\Core\Workers\TMAnalysisV2;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\SegmentUpdaterService;

class SegmentUpdaterServiceTest extends AbstractTest
{
    private function segmentUpdaterServicePath(): string
    {
        $path = realpath(self::projectRoot() . '/lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/SegmentUpdaterService.php');
        $this->assertNotFalse($path, 'SegmentUpdaterService.php must exist at expected path.');

        return $path;
    }

    private function readSource(string $path): string
    {
        $source = file_get_contents($path);
        $this->assertNotFalse($source, "Could not read source file: {$path}");

        return $source;
    }

    #[Test]
    public function test_service_can_be_instantiated_and_implements_interface(): void
    {
        $service = new SegmentUpdaterService(Database::obtain());
        $this->assertInstanceOf(SegmentUpdaterServiceInterface::class, $service);
    }

    #[Test]
    public function test_force_set_segment_analyzed_has_pdo_exception_catch_block(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $catchPos = strpos($source, 'catch (PDOException $e)');
        $this->assertNotFalse(
            $catchPos,
            'Expected PDOException catch block in forceSetSegmentAnalyzed().'
        );

        $returnInCatchPos = strpos($source, 'return 0;', $catchPos);
        $this->assertNotFalse(
            $returnInCatchPos,
            'Expected "return false;" inside PDOException catch block in forceSetSegmentAnalyzed().'
        );
    }

    #[Test]
    public function test_force_set_segment_analyzed_has_affected_rows_zero_guard(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $affectedRowsGuardPos = strpos($source, 'if ($affectedRows === 0)');
        $this->assertNotFalse(
            $affectedRowsGuardPos,
            'Expected "$affectedRows === 0" guard in forceSetSegmentAnalyzed().'
        );

        $returnOnZeroPos = strpos($source, 'isTranslationSkipped', $affectedRowsGuardPos);
        $this->assertNotFalse($returnOnZeroPos, 'Expected isTranslationSkipped guard to prevent duplicate counter increment.');

        $returnOnZeroPos = strpos($source, '? -1 : 0;', $returnOnZeroPos);
        $this->assertNotFalse($returnOnZeroPos, 'Expected isTranslationSkipped guard to prevent duplicate counter increment.');
    }

    #[Test]
    public function test_force_set_segment_analyzed_pdo_catch_appears_before_affected_rows_guard(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $catchPos = strpos($source, 'catch (PDOException $e)');
        $this->assertNotFalse($catchPos, 'Expected PDOException catch in forceSetSegmentAnalyzed().');

        $affectedRowsGuardPos = strpos($source, 'if ($affectedRows === 0)');
        $this->assertNotFalse($affectedRowsGuardPos, 'Expected $affectedRows === 0 guard in forceSetSegmentAnalyzed().');

        $this->assertLessThan(
            $affectedRowsGuardPos,
            $catchPos,
            'PDOException catch must appear before the $affectedRows === 0 guard.'
        );
    }

    #[Test]
    public function test_set_analysis_value_delegates_to_segment_translation_dao(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $this->assertStringContainsString(
            '->setAnalysisValue(',
            $source,
            'setAnalysisValue() must delegate to SegmentTranslationDao.'
        );
    }

    #[Test]
    public function test_service_implements_segment_updater_service_interface(): void
    {
        $source = $this->readSource($this->segmentUpdaterServicePath());

        $this->assertStringContainsString(
            'implements SegmentUpdaterServiceInterface',
            $source,
            'SegmentUpdaterService must declare implements SegmentUpdaterServiceInterface.'
        );
    }

    // ── DB integration tests ───────────────────────────────────────────

    private const int TEST_SEGMENT_ID = 99900;
    private const int TEST_JOB_ID = 99901;

    private function seedTestSegmentTranslation(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "
            INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool)
            VALUES (" . self::TEST_SEGMENT_ID . ", 1, '1', 'Test segment', MD5('Test segment'), 2.0, 1)
        "
        );
        $conn->exec(
            "
            INSERT IGNORE INTO segment_translations (id_segment, id_job, segment_hash, status, translation, tm_analysis_status, match_type, eq_word_count, standard_word_count, suggestion_match, suggestion_source, suggestion)
            VALUES (" . self::TEST_SEGMENT_ID . ", " . self::TEST_JOB_ID . ", MD5('Test segment'), 'NEW', '', 'UNDONE', 'NEW', NULL, NULL, NULL, NULL, '')
        "
        );
    }

    private function cleanupTestSegmentTranslation(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM segment_translations WHERE id_segment = " . self::TEST_SEGMENT_ID);
        $conn->exec("DELETE FROM segments WHERE id = " . self::TEST_SEGMENT_ID);
    }

    #[Test]
    public function setAnalysisValue_updates_segment_translation_row(): void
    {
        $this->seedTestSegmentTranslation();

        try {
            $service = new SegmentUpdaterService(Database::obtain());

            $affected = $service->setAnalysisValue([
                'id_segment' => self::TEST_SEGMENT_ID,
                'id_job' => self::TEST_JOB_ID,
                'tm_analysis_status' => 'DONE',
                'match_type' => '85%-94%',
                'eq_word_count' => 0.7,
                'standard_word_count' => 2.0,
                'suggestion_match' => 85,
                'suggestion_source' => 'TM',
                'suggestion' => 'Test translation',
            ]);

            $this->assertEquals(1, $affected);

            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare("SELECT tm_analysis_status, match_type, suggestion_source FROM segment_translations WHERE id_segment = ? AND id_job = ?");
            $stmt->execute([self::TEST_SEGMENT_ID, self::TEST_JOB_ID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals('DONE', $row['tm_analysis_status']);
            $this->assertEquals('85%-94%', $row['match_type']);
            $this->assertEquals('TM', $row['suggestion_source']);
        } finally {
            $this->cleanupTestSegmentTranslation();
        }
    }

    #[Test]
    public function setAnalysisValue_skips_already_done_segments(): void
    {
        $this->seedTestSegmentTranslation();

        try {
            $conn = Database::obtain()->getConnection();
            $conn->exec("UPDATE segment_translations SET tm_analysis_status = 'DONE' WHERE id_segment = " . self::TEST_SEGMENT_ID . " AND id_job = " . self::TEST_JOB_ID);

            $service = new SegmentUpdaterService(Database::obtain());

            $affected = $service->setAnalysisValue([
                'id_segment' => self::TEST_SEGMENT_ID,
                'id_job' => self::TEST_JOB_ID,
                'tm_analysis_status' => 'DONE',
                'match_type' => 'ICE',
                'eq_word_count' => 0.0,
            ]);

            $this->assertEquals(0, $affected);
        } finally {
            $this->cleanupTestSegmentTranslation();
        }
    }

    #[Test]
    public function forceSetSegmentAnalyzed_updates_undone_segment(): void
    {
        $this->seedTestSegmentTranslation();

        try {
            $service = new SegmentUpdaterService(Database::obtain());

            $result = $service->forceSetSegmentAnalyzed(self::TEST_SEGMENT_ID, self::TEST_JOB_ID);

            $this->assertEquals(1, $result);

            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare("SELECT tm_analysis_status FROM segment_translations WHERE id_segment = ? AND id_job = ?");
            $stmt->execute([self::TEST_SEGMENT_ID, self::TEST_JOB_ID]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->assertEquals('DONE', $row['tm_analysis_status']);
        } finally {
            $this->cleanupTestSegmentTranslation();
        }
    }

    #[Test]
    public function forceSetSegmentAnalyzed_returns_false_for_nonexistent_segment(): void
    {
        $service = new SegmentUpdaterService(Database::obtain());

        $result = $service->forceSetSegmentAnalyzed(999999, 999999);

        $this->assertEquals(0, $result);
    }

    // ── Unit tests (only for paths that need stubs — e.g., PDOException) ──

    #[Test]
    public function forceSetSegmentAnalyzed_returns_false_on_pdo_exception(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('Connection lost'));

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $service = new SegmentUpdaterService($db);

        $result = $service->forceSetSegmentAnalyzed(1, 2);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function forceSetSegmentAnalyzed_returns_false_for_already_done_segment(): void
    {
        $this->seedTestSegmentTranslation();

        try {
            $conn = Database::obtain()->getConnection();
            $conn->exec(
                "UPDATE segment_translations SET tm_analysis_status = 'DONE'"
                . " WHERE id_segment = " . self::TEST_SEGMENT_ID
                . " AND id_job = " . self::TEST_JOB_ID
            );

            $service = new SegmentUpdaterService(Database::obtain());

            $result = $service->forceSetSegmentAnalyzed(self::TEST_SEGMENT_ID, self::TEST_JOB_ID);

            // NOT IN ('DONE','SKIPPED') guard → 0 affected rows → false
            $this->assertEquals(0, $result);
        } finally {
            $this->cleanupTestSegmentTranslation();
        }
    }

    #[Test]
    public function forceSetSegmentAnalyzed_returns_minus_one_for_skipped_segment(): void
    {
        $this->seedTestSegmentTranslation();

        try {
            $conn = Database::obtain()->getConnection();
            $conn->exec(
                "UPDATE segment_translations SET tm_analysis_status = 'SKIPPED'"
                . " WHERE id_segment = " . self::TEST_SEGMENT_ID
                . " AND id_job = " . self::TEST_JOB_ID
            );

            $service = new SegmentUpdaterService(Database::obtain());

            $result = $service->forceSetSegmentAnalyzed(self::TEST_SEGMENT_ID, self::TEST_JOB_ID);

            $this->assertEquals(-1, $result);
        } finally {
            $this->cleanupTestSegmentTranslation();
        }
    }

    #[Test]
    public function constructor_accepts_idatabase_instance(): void
    {
        $db = $this->createStub(IDatabase::class);
        $service = new SegmentUpdaterService($db);

        $this->assertInstanceOf(SegmentUpdaterServiceInterface::class, $service);
    }

    // ── Coverage for SKIPPED detection and exception paths ────────────

    #[Test]
    public function setAnalysisValue_returns_minus_one_for_skipped_segment(): void
    {
        $this->seedTestSegmentTranslation();

        try {
            $conn = Database::obtain()->getConnection();
            $conn->exec(
                "UPDATE segment_translations SET tm_analysis_status = 'SKIPPED'"
                . " WHERE id_segment = " . self::TEST_SEGMENT_ID
                . " AND id_job = " . self::TEST_JOB_ID
            );

            $service = new SegmentUpdaterService(Database::obtain());

            $result = $service->setAnalysisValue([
                'id_segment' => self::TEST_SEGMENT_ID,
                'id_job' => self::TEST_JOB_ID,
                'tm_analysis_status' => 'DONE',
                'match_type' => 'ICE',
                'eq_word_count' => 0.0,
            ]);

            $this->assertEquals(-1, $result, 'setAnalysisValue must return -1 for SKIPPED segments');
        } finally {
            $this->cleanupTestSegmentTranslation();
        }
    }

    #[Test]
    public function setAnalysisValue_throws_when_id_segment_missing(): void
    {
        $service = new SegmentUpdaterService(Database::obtain());

        $this->expectException(Throwable::class);

        $service->setAnalysisValue([
            'id_job' => self::TEST_JOB_ID,
            'tm_analysis_status' => 'DONE',
            'match_type' => 'ICE',
            'eq_word_count' => 0.0,
        ]);
    }

    #[Test]
    public function setAnalysisValue_throws_when_id_job_missing(): void
    {
        $service = new SegmentUpdaterService(Database::obtain());

        $this->expectException(Throwable::class);

        $service->setAnalysisValue([
            'id_segment' => self::TEST_SEGMENT_ID,
            'tm_analysis_status' => 'DONE',
            'match_type' => 'ICE',
            'eq_word_count' => 0.0,
        ]);
    }
}
