<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\SegmentUpdaterService;

class SegmentUpdaterServiceTest extends AbstractTest
{
    private function segmentUpdaterServicePath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/SegmentUpdaterService.php');
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
        $service = new SegmentUpdaterService();
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

        $returnInCatchPos = strpos($source, 'return false;', $catchPos);
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

        $returnAfterGuardPos = strpos($source, 'return false;', $affectedRowsGuardPos);
        $this->assertNotFalse(
            $returnAfterGuardPos,
            'Expected "return false;" after $affectedRows === 0 guard in forceSetSegmentAnalyzed().'
        );
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
            'SegmentTranslationDao::setAnalysisValue(',
            $source,
            'setAnalysisValue() must delegate to SegmentTranslationDao::setAnalysisValue().'
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
}
