<?php

namespace Matecat\Core\Plugins\Features\TranslationEvents;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryWithCategoryStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\Model\TranslationEventStruct;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class TranslationEventTest extends AbstractTest
{
    private function makeTranslation(string $status = TranslationStatus::STATUS_TRANSLATED, int $versionNumber = 1): SegmentTranslationStruct
    {
        return new SegmentTranslationStruct([
            'id_segment' => 10,
            'id_job' => 1,
            'status' => $status,
            'version_number' => $versionNumber,
        ]);
    }

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

    private function makeEvent(
        ?SegmentTranslationStruct $old = null,
        ?SegmentTranslationStruct $wanted = null,
        ?UserStruct $user = null,
        int $sourcePage = SourcePages::SOURCE_PAGE_TRANSLATE,
        ?TranslationEventStruct $previousEvent = null,
    ): TranslationEvent {
        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('getLatestEventForSegment')->willReturn($previousEvent);

        return new TranslationEvent(
            $old ?? $this->makeTranslation(),
            $wanted ?? $this->makeTranslation(),
            $user,
            $sourcePage,
            $this->makeChunk(),
            $eventDao,
            $this->createStub(SegmentDao::class),
        );
    }

    #[Test]
    public function constructorSetsChunkFromInjectedParam(): void
    {
        $event = $this->makeEvent();

        $this->assertInstanceOf(JobStruct::class, $event->getChunk());
        $this->assertSame(1, $event->getChunk()->id);
    }

    #[Test]
    public function constructorRethrowsRuntimeExceptionWhenJobResolutionThrows(): void
    {
        // With no chunk injected, the constructor resolves it via wanted->getJob(new JobDao(...)).
        // The catch there was widened from Error to Throwable: a non-Error failure while obtaining
        // the job (here, the DB handle lookup throwing an Exception) must be caught and rethrown as
        // a RuntimeException instead of escaping as its original type.
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getDatabaseHandler')
            ->willThrowException(new \RuntimeException('db handle unavailable'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job not found or it is deleted');

        new TranslationEvent(
            $this->makeTranslation(),
            $this->makeTranslation(),
            null,
            SourcePages::SOURCE_PAGE_TRANSLATE,
            null, // no chunk → force the getJob() resolution path that owns the widened catch
            $this->createStub(TranslationEventDao::class),
            $segmentDao,
        );
    }

    #[Test]
    public function getWantedTranslation(): void
    {
        $wanted = $this->makeTranslation(TranslationStatus::STATUS_APPROVED);
        $event = $this->makeEvent(wanted: $wanted);

        $this->assertSame($wanted, $event->getWantedTranslation());
    }

    #[Test]
    public function getOldTranslation(): void
    {
        $old = $this->makeTranslation(TranslationStatus::STATUS_DRAFT);
        $event = $this->makeEvent(old: $old);

        $this->assertSame($old, $event->getOldTranslation());
    }

    #[Test]
    public function getUserReturnsUserWhenUidSet(): void
    {
        $user = new UserStruct(['uid' => 42]);
        $event = $this->makeEvent(user: $user);

        $this->assertSame($user, $event->getUser());
    }

    #[Test]
    public function getUserReturnsNullWhenNoUid(): void
    {
        $user = new UserStruct(['uid' => null]);
        $event = $this->makeEvent(user: $user);

        $this->assertNull($event->getUser());
    }

    #[Test]
    public function getUserReturnsNullWhenNoUser(): void
    {
        $event = $this->makeEvent(user: null);

        $this->assertNull($event->getUser());
    }

    #[Test]
    public function getSourcePage(): void
    {
        $event = $this->makeEvent(sourcePage: SourcePages::SOURCE_PAGE_REVISION);

        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION, $event->getSourcePage());
    }

    #[Test]
    public function isADraftChangeReturnsTrueForDraft(): void
    {
        $event = $this->makeEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_DRAFT),
        );

        $this->assertTrue($event->isADraftChange());
    }

    #[Test]
    public function isADraftChangeReturnsFalseForTranslated(): void
    {
        $event = $this->makeEvent(
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
        );

        $this->assertFalse($event->isADraftChange());
    }

    #[Test]
    public function isChangingStatusReturnsTrueWhenDifferent(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_DRAFT),
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
        );

        $this->assertTrue($event->isChangingStatus());
    }

    #[Test]
    public function isChangingStatusReturnsFalseWhenSame(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
        );

        $this->assertFalse($event->isChangingStatus());
    }

    #[Test]
    public function isUnModifiedIceReturnsTrueWhenBothVersionZero(): void
    {
        $old = $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED, 0);
        $old->locked = true;
        $old->match_type = 'ICE';
        $wanted = $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED, 0);

        $event = $this->makeEvent(old: $old, wanted: $wanted);

        $this->assertTrue($event->isIce());
        $this->assertTrue($event->isUnModifiedIce());
    }

    #[Test]
    public function isPreparedDefaultsFalse(): void
    {
        $event = $this->makeEvent();

        $this->assertFalse($event->isPrepared());
    }

    #[Test]
    public function setPreparedReturnsSelf(): void
    {
        $event = $this->makeEvent();
        $result = $event->setPrepared(true);

        $this->assertTrue($event->isPrepared());
        $this->assertSame($event, $result);
    }

    #[Test]
    public function getTranslationEventStructThrowsWhenNotSet(): void
    {
        $event = $this->makeEvent();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('was not prepared yet');

        $event->getTranslationEventStruct();
    }

    #[Test]
    public function setAndGetTranslationEventStruct(): void
    {
        $event = $this->makeEvent();
        $struct = new TranslationEventStruct([
            'source_page' => SourcePages::SOURCE_PAGE_TRANSLATE,
        ]);

        $result = $event->setTranslationEventStruct($struct);

        $this->assertSame($struct, $event->getTranslationEventStruct());
        $this->assertSame($event, $result);
    }

    #[Test]
    public function getPreviousEventSourcePageFromPriorEvent(): void
    {
        $priorEvent = new TranslationEventStruct([
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
        ]);
        $event = $this->makeEvent(previousEvent: $priorEvent);

        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION, $event->getPreviousEventSourcePage());
    }

    #[Test]
    public function getPreviousEventSourcePageGuessesTranslateFromTranslatedStatus(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            previousEvent: null,
        );

        $this->assertSame(SourcePages::SOURCE_PAGE_TRANSLATE, $event->getPreviousEventSourcePage());
    }

    #[Test]
    public function getPreviousEventSourcePageGuessesTranslateFromDraftStatus(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_DRAFT),
            previousEvent: null,
        );

        $this->assertSame(SourcePages::SOURCE_PAGE_TRANSLATE, $event->getPreviousEventSourcePage());
    }

    #[Test]
    public function getPreviousEventSourcePageGuessesRevisionFromApproved(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_APPROVED),
            previousEvent: null,
        );

        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION, $event->getPreviousEventSourcePage());
    }

    #[Test]
    public function getPreviousEventSourcePageGuessesRevision2FromApproved2(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_APPROVED2),
            previousEvent: null,
        );

        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION_2, $event->getPreviousEventSourcePage());
    }

    #[Test]
    public function isLowerTransitionReturnsTrueWhenDowngrade(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_APPROVED),
            wanted: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
        );

        $this->assertTrue($event->isLowerTransition());
    }

    #[Test]
    public function isLowerTransitionReturnsFalseWhenUpgrade(): void
    {
        $event = $this->makeEvent(
            old: $this->makeTranslation(TranslationStatus::STATUS_TRANSLATED),
            wanted: $this->makeTranslation(TranslationStatus::STATUS_APPROVED),
        );

        $this->assertFalse($event->isLowerTransition());
    }

    #[Test]
    public function propagationSourceDefaultsTrue(): void
    {
        $event = $this->makeEvent();

        $this->assertTrue($event->isPropagationSource());
    }

    #[Test]
    public function setPropagationSource(): void
    {
        $event = $this->makeEvent();
        $event->setPropagationSource(false);

        $this->assertFalse($event->isPropagationSource());
    }

    #[Test]
    public function revisionFlagAllowedDefaultsTrue(): void
    {
        $event = $this->makeEvent();

        $this->assertTrue($event->isFinalRevisionFlagAllowed());
    }

    #[Test]
    public function setRevisionFlagAllowed(): void
    {
        $event = $this->makeEvent();
        $result = $event->setRevisionFlagAllowed(false);

        $this->assertFalse($event->isFinalRevisionFlagAllowed());
        $this->assertSame($event, $result);
    }

    #[Test]
    public function setFinalRevisionToRemoveAccumulates(): void
    {
        $event = $this->makeEvent();
        $event->setFinalRevisionToRemove(SourcePages::SOURCE_PAGE_REVISION);
        $event->setFinalRevisionToRemove(SourcePages::SOURCE_PAGE_REVISION_2);

        $this->assertSame([SourcePages::SOURCE_PAGE_REVISION, SourcePages::SOURCE_PAGE_REVISION_2], $event->getUnsetFinalRevision());
    }

    #[Test]
    public function setChunkReviewForPassFailUpdateDeduplicates(): void
    {
        $event = $this->makeEvent();
        $review = new ChunkReviewStruct(['id' => 1]);
        $event->setChunkReviewForPassFailUpdate($review);
        $event->setChunkReviewForPassFailUpdate($review);

        $this->assertCount(1, $event->getChunkReviewsPartials());
    }

    #[Test]
    public function addIssueToDeleteDeduplicates(): void
    {
        $event = $this->makeEvent();
        $issue = new EntryWithCategoryStruct(['id' => 5]);
        $event->addIssueToDelete($issue);
        $event->addIssueToDelete($issue);

        $this->assertCount(1, $event->getIssuesToDelete());
    }

    #[Test]
    public function getSegmentStructUsesInjectedDao(): void
    {
        $segment = new SegmentStruct(['id' => 10]);
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getByChunkIdAndSegmentId')->willReturn($segment);

        $eventDao = $this->createStub(TranslationEventDao::class);
        $eventDao->method('getLatestEventForSegment')->willReturn(null);

        $event = new TranslationEvent(
            $this->makeTranslation(),
            $this->makeTranslation(),
            null,
            SourcePages::SOURCE_PAGE_TRANSLATE,
            $this->makeChunk(),
            $eventDao,
            $segmentDao,
        );

        $this->assertSame($segment, $event->getSegmentStruct());
    }

    #[Test]
    public function currentEventIsOnThisChunkMatchesSourcePage(): void
    {
        $event = $this->makeEvent();
        $struct = new TranslationEventStruct([
            'source_page' => SourcePages::SOURCE_PAGE_TRANSLATE,
        ]);
        $event->setTranslationEventStruct($struct);

        $review = new ChunkReviewStruct(['source_page' => SourcePages::SOURCE_PAGE_TRANSLATE]);

        $this->assertTrue($event->currentEventIsOnThisChunk($review));
    }

}
