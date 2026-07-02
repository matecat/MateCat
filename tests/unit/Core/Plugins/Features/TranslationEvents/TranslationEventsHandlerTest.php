<?php

namespace Matecat\Core\Plugins\Features\TranslationEvents;

use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\Model\TranslationEventStruct;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class TestableTranslationEventsHandler extends TranslationEventsHandler
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

class TranslationEventsHandlerTest extends AbstractTest
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

    private function makeTranslation(string $status = TranslationStatus::STATUS_TRANSLATED, int $timeToEdit = 100): SegmentTranslationStruct
    {
        return new SegmentTranslationStruct([
            'id_segment' => 10,
            'id_job' => 1,
            'status' => $status,
            'version_number' => 1,
            'time_to_edit' => $timeToEdit,
        ]);
    }

    private function makeTranslationEvent(
        ?SegmentTranslationStruct $old = null,
        ?SegmentTranslationStruct $wanted = null,
        ?UserStruct $user = null,
        int $sourcePage = SourcePages::SOURCE_PAGE_TRANSLATE,
    ): TranslationEvent {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('getLatestEventForSegment')->willReturn(null);

        $segmentDao = $this->createStub(\Model\Segments\SegmentDao::class);

        return new TranslationEvent(
            $old ?? $this->makeTranslation(),
            $wanted ?? $this->makeTranslation(),
            $user,
            $sourcePage,
            $this->makeChunk(),
            $eventDao,
            $segmentDao,
        );
    }

    private function makeHandler(?TranslationEventDao $dao = null): TestableTranslationEventsHandler
    {
        return new TestableTranslationEventsHandler(
            $this->makeChunk(),
            $dao ?? $this->createStub(TranslationEventDao::class),
        );
    }

    #[Test]
    public function addEventAndGetEvents(): void
    {
        $handler = $this->makeHandler();
        $event = $this->makeTranslationEvent();
        $handler->addEvent($event);

        $this->assertCount(1, $handler->getEvents());
        $this->assertSame($event, $handler->getEvents()[0]);
    }

    #[Test]
    public function getPreparedEventsFiltersUnprepared(): void
    {
        $handler = $this->makeHandler();

        $prepared = $this->makeTranslationEvent();
        $prepared->setPrepared(true);
        $prepared->setTranslationEventStruct(new TranslationEventStruct(['source_page' => 1]));

        $unprepared = $this->makeTranslationEvent();

        $handler->addEvent($prepared);
        $handler->addEvent($unprepared);

        $this->assertCount(1, $handler->getPreparedEvents());
    }

    #[Test]
    public function setAndGetProject(): void
    {
        $handler = $this->makeHandler();
        $project = $this->createStub(\Model\Projects\ProjectStruct::class);
        $result = $handler->setProject($project);

        $this->assertSame($project, $handler->getProject());
        $this->assertSame($handler, $result);
    }

    #[Test]
    public function prepareEventStructSetsAllFields(): void
    {
        $handler = $this->makeHandler();
        $user = new UserStruct(['uid' => 42]);
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED, 500),
            user: $user,
            sourcePage: SourcePages::SOURCE_PAGE_TRANSLATE,
        );

        $handler->prepareEventStruct($event);

        $this->assertTrue($event->isPrepared());
        $struct = $event->getTranslationEventStruct();
        $this->assertSame(1, $struct->id_job);
        $this->assertSame(10, $struct->id_segment);
        $this->assertSame(42, $struct->uid);
        $this->assertSame(TranslationStatus::STATUS_TRANSLATED, $struct->status);
        $this->assertSame(1, $struct->version_number);
        $this->assertSame(SourcePages::SOURCE_PAGE_TRANSLATE, $struct->source_page);
        $this->assertSame(500, $struct->time_to_edit);
    }

    #[Test]
    public function prepareEventStructSkipsTimeToEditWhenNotPropagationSource(): void
    {
        $handler = $this->makeHandler();
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED, 500),
        );
        $event->setPropagationSource(false);

        $handler->prepareEventStruct($event);

        $struct = $event->getTranslationEventStruct();
        $this->assertFalse(isset($struct->time_to_edit));
    }

    #[Test]
    public function prepareEventStructSetsUidZeroWhenNoUser(): void
    {
        $handler = $this->makeHandler();
        $event = $this->makeTranslationEvent(user: null);

        $handler->prepareEventStruct($event);

        $this->assertSame(0, $event->getTranslationEventStruct()->uid);
    }

    #[Test]
    public function prepareEventStructThrowsOnRevisedFromTranslate(): void
    {
        $handler = $this->makeHandler();
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_APPROVED),
            sourcePage: SourcePages::SOURCE_PAGE_TRANSLATE,
        );

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Setting revised state from translation is not allowed.');

        $handler->prepareEventStruct($event);
    }

    #[Test]
    public function prepareEventStructThrowsOnTranslatedFromRevision(): void
    {
        $handler = $this->makeHandler();
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            sourcePage: SourcePages::SOURCE_PAGE_REVISION,
        );

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Setting translated state from revision is not allowed.');

        $handler->prepareEventStruct($event);
    }

    #[Test]
    public function saveCallsProcessAndSavesEvents(): void
    {
        $dao = $this->createMock(TranslationEventDao::class);
        $dao->expects($this->once())
            ->method('insertStruct')
            ->willReturn(99);

        $handler = $this->makeHandler($dao);
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            sourcePage: SourcePages::SOURCE_PAGE_TRANSLATE,
        );
        $handler->addEvent($event);

        $batchProcessor = $this->createMock(BatchReviewProcessor::class);
        $batchProcessor->expects($this->once())->method('process');

        $handler->save($batchProcessor);

        $this->assertTrue($event->isPrepared());
    }

    #[Test]
    public function saveSetsCorrectFinalRevisionFlagForTranslate(): void
    {
        $dao = $this->createMock(TranslationEventDao::class);
        $dao->expects($this->once())
            ->method('insertStruct')
            ->with($this->callback(function (TranslationEventStruct $struct) {
                return $struct->final_revision === 0;
            }))
            ->willReturn(1);

        $handler = $this->makeHandler($dao);
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            sourcePage: SourcePages::SOURCE_PAGE_TRANSLATE,
        );
        $handler->addEvent($event);

        $batchProcessor = $this->createStub(BatchReviewProcessor::class);
        $handler->save($batchProcessor);
    }

    #[Test]
    public function saveSetsCorrectFinalRevisionFlagForRevision(): void
    {
        $dao = $this->createMock(TranslationEventDao::class);
        $dao->expects($this->once())
            ->method('insertStruct')
            ->with($this->callback(function (TranslationEventStruct $struct) {
                return $struct->final_revision === 1;
            }))
            ->willReturn(1);

        $handler = $this->makeHandler($dao);
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_APPROVED),
            sourcePage: SourcePages::SOURCE_PAGE_REVISION,
        );
        $handler->addEvent($event);

        $batchProcessor = $this->createStub(BatchReviewProcessor::class);
        $handler->save($batchProcessor);
    }

    #[Test]
    public function saveSetsZeroFinalRevisionWhenFlagNotAllowed(): void
    {
        $dao = $this->createMock(TranslationEventDao::class);
        $dao->expects($this->once())
            ->method('insertStruct')
            ->with($this->callback(function (TranslationEventStruct $struct) {
                return $struct->final_revision === 0;
            }))
            ->willReturn(1);

        $handler = $this->makeHandler($dao);
        $event = $this->makeTranslationEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_APPROVED),
            sourcePage: SourcePages::SOURCE_PAGE_REVISION,
        );
        $event->setRevisionFlagAllowed(false);
        $handler->addEvent($event);

        $batchProcessor = $this->createStub(BatchReviewProcessor::class);
        $handler->save($batchProcessor);
    }

    #[Test]
    public function saveRemovesOldFinalRevisionFlag(): void
    {
        $segment = new SegmentStruct(['id' => 10]);
        $segmentDao = $this->createStub(\Model\Segments\SegmentDao::class);
        $segmentDao->method('getByChunkIdAndSegmentId')->willReturn($segment);

        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('getLatestEventForSegment')->willReturn(null);

        $translationEvent = new TranslationEvent(
            $this->makeTranslation(),
            $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            null,
            SourcePages::SOURCE_PAGE_TRANSLATE,
            $this->makeChunk(),
            $eventDao,
            $segmentDao,
        );
        $translationEvent->setFinalRevisionToRemove(SourcePages::SOURCE_PAGE_REVISION);

        $dao = $this->createMock(TranslationEventDao::class);
        $dao->expects($this->once())
            ->method('unsetFinalRevisionFlag')
            ->with(1, [10], [SourcePages::SOURCE_PAGE_REVISION]);
        $dao->method('insertStruct')->willReturn(1);

        $handler = $this->makeHandler($dao);
        $handler->addEvent($translationEvent);

        $batchProcessor = $this->createStub(BatchReviewProcessor::class);
        $handler->save($batchProcessor);
    }

    #[Test]
    public function getChunkReturnsInjectedChunk(): void
    {
        $handler = $this->makeHandler();
        $this->assertSame(1, $handler->getChunk()->id);
    }
}
