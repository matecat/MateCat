<?php

namespace Matecat\Core\Plugins\Features\TranslationVersions;

use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use Plugins\Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionStruct;
use RuntimeException;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class TestableTranslationVersionsHandler extends TranslationVersionsHandler
{
    private ?TranslationEventsHandler $eventsHandlerOverride = null;
    private ?BatchReviewProcessor $batchReviewProcessorOverride = null;
    private ?TranslationEventDao $translationEventDaoStub = null;

    public function setEventsHandlerOverride(TranslationEventsHandler $handler): void
    {
        $this->eventsHandlerOverride = $handler;
    }

    public function setBatchReviewProcessorOverride(BatchReviewProcessor $processor): void
    {
        $this->batchReviewProcessorOverride = $processor;
    }

    public function setTranslationEventDaoStub(TranslationEventDao $dao): void
    {
        $this->translationEventDaoStub = $dao;
    }

    protected function createTranslationEvent(
        SegmentTranslationStruct $old_translation,
        SegmentTranslationStruct $translation,
        $user,
        int $source_page_code,
        JobStruct $chunk,
    ): TranslationEvent {
        return new TranslationEvent(
            $old_translation,
            $translation,
            $user,
            $source_page_code,
            $chunk,
            $this->translationEventDaoStub,
        );
    }

    protected function createTranslationEventsHandler(JobStruct $chunk): TranslationEventsHandler
    {
        return $this->eventsHandlerOverride ?? parent::createTranslationEventsHandler($chunk);
    }

    protected function createBatchReviewProcessor(): BatchReviewProcessor
    {
        return $this->batchReviewProcessorOverride ?? parent::createBatchReviewProcessor();
    }
}

class TestableEventsHandler extends TranslationEventsHandler
{
    protected function openTransaction(): void
    {
    }

    protected function commitTransaction(): void
    {
    }

    protected function rollbackTransaction(): void
    {
    }
}

class TranslationVersionsHandlerTest extends AbstractTest
{
    private function makeChunk(): JobStruct
    {
        return new JobStruct([
            'id' => 1,
            'id_project' => 100,
            'password' => 'pwd',
            'job_first_segment' => 1,
            'job_last_segment' => 10,
            'source' => 'en-US',
            'target' => 'it-IT',
            'create_date' => '2026-01-01 00:00:00',
            'last_update' => '2026-01-01 00:00:00',
        ]);
    }

    private function makeProject(): ProjectStruct
    {
        return $this->createStub(ProjectStruct::class);
    }

    private function makeTranslation(
        string $translation = 'hello',
        string $status = TranslationStatus::STATUS_TRANSLATED,
        ?int $versionNumber = 1,
    ): SegmentTranslationStruct {
        return new SegmentTranslationStruct([
            'id_segment' => 10,
            'id_job' => 1,
            'translation' => $translation,
            'status' => $status,
            'version_number' => $versionNumber,
        ]);
    }

    private function makeHandler(
        ?TranslationVersionDao $dao = null,
        ?SegmentTranslationDao $segTransDao = null,
        ?JobDao $jobDao = null,
        ?ProjectDao $projectDao = null,
    ): TestableTranslationVersionsHandler {
        $handler = new TestableTranslationVersionsHandler(
            $this->makeChunk(),
            10,
            $this->makeProject(),
            $dao,
            $segTransDao,
            $jobDao,
            $projectDao,
        );

        $eventDaoStub = $this->createStub(TranslationEventDao::class);
        $eventDaoStub->method('getLatestEventForSegment')->willReturn(null);
        $handler->setTranslationEventDaoStub($eventDaoStub);

        return $handler;
    }

    #[Test]
    public function constructorThrowsOnMissingJobId(): void
    {
        $chunk = new JobStruct([
            'id' => null,
            'id_project' => 100,
            'password' => 'pwd',
            'job_first_segment' => 1,
            'job_last_segment' => 10,
            'source' => 'en-US',
            'target' => 'it-IT',
            'create_date' => '2026-01-01 00:00:00',
            'last_update' => '2026-01-01 00:00:00',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job id is required');

        new TranslationVersionsHandler($chunk, 10, $this->makeProject());
    }

    #[Test]
    public function constructorThrowsOnMissingSegmentId(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Segment id is required');

        new TranslationVersionsHandler($this->makeChunk(), null, $this->makeProject());
    }

    #[Test]
    public function saveVersionAndIncrementReturnsFalseWhenTranslationUnchanged(): void
    {
        $dao = $this->createStub(TranslationVersionDao::class);
        $handler = $this->makeHandler($dao);

        $old = $this->makeTranslation('same text', TranslationStatus::STATUS_DRAFT, 1);
        $new = $this->makeTranslation('same text', TranslationStatus::STATUS_TRANSLATED, 1);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertFalse($result);
        $this->assertSame(1, $new->version_number);
    }

    #[Test]
    public function saveVersionAndIncrementReturnsTrueAndIncrementsVersion(): void
    {
        $dao = $this->createStub(TranslationVersionDao::class);
        $handler = $this->makeHandler($dao);

        $old = $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED, 2);
        $new = $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED, 2);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(3, $new->version_number);
    }

    /**
     * Regression: before the fix, a concurrent write that caused updateVersion() to return 0
     * ("no rows changed" — identical translation already saved by a racing request) would
     * suppress the version increment. upsertVersion() always succeeds and the handler always
     * returns true when the translation text changed.
     */
    #[Test]
    public function saveVersionAndIncrementReturnsTrueEvenWhenConcurrentWriteOccurred(): void
    {
        $dao = $this->createMock(TranslationVersionDao::class);
        $dao->expects($this->once())->method('upsertVersion');

        $handler = $this->makeHandler($dao);

        $old = $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED, 1);
        $new = $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED, 1);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(2, $new->version_number);
    }

    #[Test]
    public function saveVersionAndIncrementHandlesNullVersionNumbers(): void
    {
        $dao = $this->createStub(TranslationVersionDao::class);
        $handler = $this->makeHandler($dao);

        $old = $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED, null);
        $new = $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED, null);

        $result = $handler->saveVersionAndIncrement($new, $old);

        $this->assertTrue($result);
        $this->assertSame(1, $new->version_number);
    }

    #[Test]
    public function saveVersionAndIncrementKeepsVersionWhenNotSaved(): void
    {
        $dao = $this->createStub(TranslationVersionDao::class);
        $handler = $this->makeHandler($dao);

        $old = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, 5);
        $new = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, 5);

        $handler->saveVersionAndIncrement($new, $old);

        $this->assertSame(5, $new->version_number);
    }

    #[Test]
    public function saveVersionAndIncrementKeepsZeroWhenOldVersionNull(): void
    {
        $dao = $this->createStub(TranslationVersionDao::class);
        $handler = $this->makeHandler($dao);

        $old = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, null);
        $new = $this->makeTranslation('same', TranslationStatus::STATUS_TRANSLATED, null);

        $handler->saveVersionAndIncrement($new, $old);

        $this->assertSame(0, $new->version_number);
    }

    #[Test]
    public function propagateTranslationDelegatesToDao(): void
    {
        $segTransDao = $this->createStub(SegmentTranslationDao::class);
        $segTransDao->method('propagateTranslation')->willReturn(['propagated' => true]);

        $handler = $this->makeHandler(segTransDao: $segTransDao);

        $result = $handler->propagateTranslation($this->makeTranslation());

        $this->assertSame(['propagated' => true], $result);
    }

    #[Test]
    public function storeTranslationEventSavesSourceEvent(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willReturn(1);

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $jobDao = $this->createStub(JobDao::class);
        $projectDao = $this->createStub(ProjectDao::class);

        $handler = $this->makeHandler(jobDao: $jobDao, projectDao: $projectDao);
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $handler->storeTranslationEvent([
            'user' => null,
            'translation' => $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED),
            'old_translation' => $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk' => $this->makeChunk(),
            'features' => $this->createStub(FeatureSet::class),
            'project' => $this->makeProject(),
        ]);

        $this->assertCount(1, $eventsHandler->getEvents());
    }

    #[Test]
    public function storeTranslationEventWithPropagation(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willReturn(1);

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $jobDao = $this->createStub(JobDao::class);
        $projectDao = $this->createStub(ProjectDao::class);

        $handler = $this->makeHandler(jobDao: $jobDao, projectDao: $projectDao);
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $propagatedSegment = $this->makeTranslation('propagated text', TranslationStatus::STATUS_TRANSLATED);

        $handler->storeTranslationEvent([
            'user' => new UserStruct(['uid' => 42]),
            'translation' => $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED),
            'old_translation' => $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk' => $this->makeChunk(),
            'features' => $this->createStub(FeatureSet::class),
            'project' => $this->makeProject(),
            'propagation' => [
                'segments_for_propagation' => [
                    'propagated' => [
                        'not_ice' => ['object' => [$propagatedSegment]],
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $eventsHandler->getEvents());
    }

    #[Test]
    public function storeTranslationEventWithIcePropagation(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willReturn(1);

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $jobDao = $this->createStub(JobDao::class);
        $projectDao = $this->createStub(ProjectDao::class);

        $handler = $this->makeHandler(jobDao: $jobDao, projectDao: $projectDao);
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $iceSegment = $this->makeTranslation('ice text', TranslationStatus::STATUS_TRANSLATED);
        $notIceSegment = $this->makeTranslation('not ice text', TranslationStatus::STATUS_TRANSLATED);

        $handler->storeTranslationEvent([
            'user' => null,
            'translation' => $this->makeTranslation('new text', TranslationStatus::STATUS_TRANSLATED),
            'old_translation' => $this->makeTranslation('old text', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk' => $this->makeChunk(),
            'features' => $this->createStub(FeatureSet::class),
            'project' => $this->makeProject(),
            'propagation' => [
                'segments_for_propagation' => [
                    'propagated' => [
                        'not_ice' => ['object' => [$notIceSegment]],
                        'ice' => ['object' => [$iceSegment]],
                    ],
                ],
            ],
        ]);

        $this->assertCount(3, $eventsHandler->getEvents());
    }

    #[Test]
    public function storeTranslationEventWrapsExceptionInRuntimeException(): void
    {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('insertStruct')->willThrowException(new \Exception('DB error'));

        $eventsHandler = new TestableEventsHandler($this->makeChunk(), $eventDao);

        $handler = $this->makeHandler();
        $handler->setEventsHandlerOverride($eventsHandler);
        $handler->setBatchReviewProcessorOverride($this->createStub(BatchReviewProcessor::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB error');
        $this->expectExceptionCode(-2000);

        $handler->storeTranslationEvent([
            'user' => null,
            'translation' => $this->makeTranslation('new', TranslationStatus::STATUS_TRANSLATED),
            'old_translation' => $this->makeTranslation('old', TranslationStatus::STATUS_TRANSLATED),
            'source_page_code' => SourcePages::SOURCE_PAGE_TRANSLATE,
            'chunk' => $this->makeChunk(),
            'features' => $this->createStub(FeatureSet::class),
            'project' => $this->makeProject(),
        ]);
    }
}
