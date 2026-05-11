<?php

use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;

class ProjectCompletionServiceTest extends AbstractTest
{

    private function sourcePath(): string
    {
        $path = realpath(__DIR__ . '/../../../../lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionService.php');
        $this->assertNotFalse($path, 'ProjectCompletionService.php must exist at expected path.');

        return $path;
    }

    private function readSource(): string
    {
        $source = file_get_contents($this->sourcePath());
        $this->assertNotFalse($source, 'Could not read ProjectCompletionService.php source.');

        return $source;
    }

    private function makeRedisService(): AnalysisRedisServiceInterface
    {
        return $this->createMock(AnalysisRedisServiceInterface::class);
    }

    private function makeService(?AnalysisRedisServiceInterface $redisService = null): ProjectCompletionService
    {
        $queueHandler = $this->createStub(AMQHandler::class);
        $redisService ??= $this->createStub(AnalysisRedisServiceInterface::class);

        return new ProjectCompletionService($queueHandler, $redisService);
    }

    #[Test]
    public function test_service_implements_project_completion_service_interface(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'implements ProjectCompletionServiceInterface',
            $source,
            'ProjectCompletionService must declare implements ProjectCompletionServiceInterface.'
        );
    }

    #[Test]
    public function test_try_close_project_catches_throwable(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'catch (\Throwable $e)',
            $source,
            'tryCloseProject must contain a catch (\Throwable $e) block to handle finalization failures.'
        );
    }

    #[Test]
    public function test_rollback_is_called_inside_throwable_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (\Throwable $e)');
        $this->assertNotFalse($catchPos, 'Expected catch (\Throwable $e) block in tryCloseProject.');

        $rollbackPos = strpos($source, '->rollback()', $catchPos);
        $this->assertNotFalse(
            $rollbackPos,
            'Expected ->rollback() call inside the \Throwable catch block.'
        );
    }

    #[Test]
    public function test_release_completion_lock_is_called_inside_throwable_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (\Throwable $e)');
        $this->assertNotFalse($catchPos, 'Expected catch (\Throwable $e) block in tryCloseProject.');

        $releasePos = strpos($source, 'releaseCompletionLock(', $catchPos);
        $this->assertNotFalse(
            $releasePos,
            'Expected releaseCompletionLock() call inside the \Throwable catch block.'
        );
    }

    #[Test]
    public function test_re_add_project_to_queue_is_called_inside_throwable_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (\Throwable $e)');
        $this->assertNotFalse($catchPos, 'Expected catch (\Throwable $e) block in tryCloseProject.');

        $reAddPos = strpos($source, 'reAddProjectToQueue(', $catchPos);
        $this->assertNotFalse(
            $reAddPos,
            'Expected reAddProjectToQueue() call inside the \Throwable catch block.'
        );
    }

    #[Test]
    public function test_rollback_appears_before_release_and_re_add_in_catch(): void
    {
        $source = $this->readSource();

        $catchPos = strpos($source, 'catch (\Throwable $e)');
        $this->assertNotFalse($catchPos);

        $rollbackPos  = strpos($source, '->rollback()', $catchPos);
        $releasePos   = strpos($source, 'releaseCompletionLock(', $catchPos);
        $reAddPos     = strpos($source, 'reAddProjectToQueue(', $catchPos);

        $this->assertNotFalse($rollbackPos, 'Expected ->rollback() in catch block.');
        $this->assertNotFalse($releasePos, 'Expected releaseCompletionLock() in catch block.');
        $this->assertNotFalse($reAddPos, 'Expected reAddProjectToQueue() in catch block.');

        $this->assertLessThan($releasePos, $rollbackPos, 'rollback() must appear before releaseCompletionLock().');
        $this->assertLessThan($reAddPos, $releasePos, 'releaseCompletionLock() must appear before reAddProjectToQueue().');
    }

    #[Test]
    public function test_feature_hook_exception_is_swallowed_not_rethrown(): void
    {
        $source = $this->readSource();

        $hookPos = strpos($source, 'afterTMAnalysisCloseProject');
        $this->assertNotFalse($hookPos, 'Expected afterTMAnalysisCloseProject hook call in tryCloseProject.');

        $innerCatchPos = strpos($source, 'catch (Exception $e)', $hookPos);
        $this->assertNotFalse(
            $innerCatchPos,
            'Expected a catch (Exception $e) block swallowing exceptions thrown by the feature hook.'
        );

        $outerCatchPos = strpos($source, 'catch (\Throwable $e)');
        $this->assertNotFalse($outerCatchPos);

        $this->assertGreaterThan(
            $hookPos,
            $innerCatchPos,
            'Inner catch(Exception) must appear after the hook call.'
        );

        $this->assertLessThan(
            $outerCatchPos,
            $innerCatchPos,
            'Inner catch(Exception) for the feature hook must appear before the outer catch(\Throwable).'
        );
    }

    #[Test]
    public function test_get_project_segments_summary_uses_group_by_rollup_sql(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'GROUP BY id_job WITH ROLLUP',
            $source,
            'getProjectSegmentsTranslationSummary() must use GROUP BY id_job WITH ROLLUP to produce totals row.'
        );
    }

    #[Test]
    public function test_array_pop_used_to_extract_rollup_totals_row(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'array_pop(',
            $source,
            'array_pop() must be used to extract the ROLLUP totals row from the query result set.'
        );
    }

    #[Test]
    public function test_private_method_get_project_segments_translation_summary_declared(): void
    {
        $source = $this->readSource();
        $this->assertStringContainsString(
            'private function getProjectSegmentsTranslationSummary(',
            $source,
            'Expected private method getProjectSegmentsTranslationSummary() to be declared in ProjectCompletionService.'
        );
    }

    #[Test]
    public function test_db_derived_rollup_fields_eq_wc_and_st_wc_used_for_project_update(): void
    {
        $source = $this->readSource();

        $rollupArrayPop = strpos($source, 'array_pop(');
        $this->assertNotFalse($rollupArrayPop);

        $eqWcUsage = strpos($source, "['eq_wc']", $rollupArrayPop);
        $stWcUsage = strpos($source, "['st_wc']", $rollupArrayPop);

        $this->assertNotFalse($eqWcUsage, "Expected rollup['eq_wc'] usage after array_pop.");
        $this->assertNotFalse($stWcUsage, "Expected rollup['st_wc'] usage after array_pop.");
    }

    #[Test]
    public function test_empty_project_segments_causes_early_return_before_lock(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['project_segments' => '', 'num_analyzed' => 0]);

        $redisService->expects($this->never())
            ->method('acquireCompletionLock');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(1, 'secret', 'queue:key', $featureSet);
    }

    #[Test]
    public function test_absent_project_segments_key_causes_early_return_before_lock(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['num_analyzed' => 3]);

        $redisService->expects($this->never())
            ->method('acquireCompletionLock');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(42, 'secret', 'queue:key', $featureSet);
    }

    #[Test]
    public function test_segments_not_fully_analyzed_does_not_acquire_completion_lock(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['project_segments' => 10, 'num_analyzed' => 7]);

        $redisService->expects($this->never())
            ->method('acquireCompletionLock');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(7, 'pass', 'queue:k', $featureSet);
    }

    #[Test]
    public function test_fully_analyzed_but_lock_not_acquired_does_not_start_db_transaction(): void
    {
        $redisService = $this->makeRedisService();
        $redisService->method('getProjectWordCounts')
            ->willReturn(['project_segments' => 5, 'num_analyzed' => 5]);

        $redisService->method('acquireCompletionLock')
            ->willReturn(false);

        $redisService->expects($this->never())
            ->method('removeProjectFromQueue');

        $featureSet = $this->createStub(FeatureSet::class);
        $service    = $this->makeService($redisService);

        $service->tryCloseProject(99, 'pwd', 'q', $featureSet);
    }

    #[Test]
    public function test_service_can_be_instantiated_and_implements_interface(): void
    {
        $service = $this->makeService();
        $this->assertInstanceOf(ProjectCompletionServiceInterface::class, $service);
    }
}
