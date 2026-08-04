<?php

namespace Matecat\Core\Features\ReviewExtended;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryWithCategoryStruct;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\WordCount\CounterModel;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\ReviewedWordCountModel;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventStruct;
use RuntimeException;

class ReviewedWordCountModelTest extends AbstractTest
{
    private IDatabase $dbStub;
    private \PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->dbStub, , $this->stmtStub] = $this->createDatabaseMock();
        $this->stmtStub->method('fetchAll')->willReturn([]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Constructor null guards
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function constructor_throwsWhenChunkIsNull(): void
    {
        $event = $this->createStub(TranslationEvent::class);
        $event->method('getChunk')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Chunk is required');

        new ReviewedWordCountModel($event, $this->createStub(CounterModel::class), [], $this->createStub(IDatabase::class));
    }

    #[Test]
    public function constructor_throwsWhenSegmentStructIsNull(): void
    {
        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 1;
        $chunk->method('getProject')->willReturn($this->createStub(\Model\Projects\ProjectStruct::class));

        $event = $this->createStub(TranslationEvent::class);
        $event->method('getChunk')->willReturn($chunk);
        $event->method('getSegmentStruct')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Segment is required');

        new ReviewedWordCountModel($event, $this->createStub(CounterModel::class), [], $this->createStub(IDatabase::class));
    }

    // ─────────────────────────────────────────────────────────────────
    // evaluateChunkReviewEventTransitions — draft change (no-op branch)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function evaluateChunkReviewEventTransitions_draftChangeSkipsAllLogic(): void
    {
        $model = $this->buildModel(isDraft: true);

        $model->evaluateChunkReviewEventTransitions();

        $this->assertEmpty($model->getEvent()->getChunkReviewsPartials());
    }

    // ─────────────────────────────────────────────────────────────────
    // evaluateChunkReviewEventTransitions — changing status path
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function evaluateChunkReviewEventTransitions_changingStatusSetsWordCounter(): void
    {
        $counterModel = $this->createMock(CounterModel::class);
        $counterModel->expects($this->once())->method('setOldStatus');
        $counterModel->expects($this->once())->method('setNewStatus');
        $counterModel->expects($this->once())->method('setUpdatedValues');

        $model = $this->buildModel(isChangingStatus: true, counterModel: $counterModel);

        $model->evaluateChunkReviewEventTransitions();
    }

    // ─────────────────────────────────────────────────────────────────
    // evaluateChunkReviewEventTransitions — ICE unmodified (no flag)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function evaluateChunkReviewEventTransitions_unmodifiedIceOnSameChunkDisallowsRevisionFlag(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setRevisionFlagAllowed')->with(false);

        $model = $this->buildModel(
            isIce: true,
            isUnModifiedIce: true,
            currentEventOnChunk: true,
            event: $event
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    // ─────────────────────────────────────────────────────────────────
    // evaluateChunkReviewEventTransitions — translation status (no-op)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function evaluateChunkReviewEventTransitions_translationStatusDisallowsRevisionFlag(): void
    {
        $wantedTranslation = $this->createStub(SegmentTranslationStruct::class);
        $wantedTranslation->status = 'TRANSLATED';
        $wantedTranslation->translation = 'Same translation';
        $wantedTranslation->method('isTranslationStatus')->willReturn(true);

        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setRevisionFlagAllowed')->with(false);

        $model = $this->buildModel(
            isChangingStatus: false,
            isIce: false,
            currentEventOnChunk: false,
            event: $event,
            wantedTranslation: $wantedTranslation
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    // ─────────────────────────────────────────────────────────────────
    // getEvent accessor
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function getEvent_returnsInjectedEvent(): void
    {
        $model = $this->buildModel();

        $this->assertInstanceOf(TranslationEvent::class, $model->getEvent());
    }

    // ─────────────────────────────────────────────────────────────────
    // deleteIssues
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function deleteIssues_withNoIssuesDoesNothing(): void
    {
        $model = $this->buildModel();

        $model->deleteIssues();
        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────────
    // sendNotificationEmail
    // ─────────────────────────────────────────────────────────────────

    /**
     * A propagated event is one of the copies of a translation the user applied elsewhere: the segment
     * they actually edited already raised its own notification, so this one must not raise a second.
     * The assertion is on the short circuit itself — the gate must not even ask about the transition.
     */
    #[Test]
    public function sendNotificationEmail_skipsWhenTheEventIsAPropagatedOne(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->never())->method('isLowerTransition');

        $model = $this->buildModel(isAPropagatedEvent: true, event: $event);

        $model->sendNotificationEmail();
    }

    #[Test]
    public function sendNotificationEmail_skipsWhenNotLowerTransition(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('isLowerTransition')->willReturn(false);
        // getUser() is the first thing the mail body does, so never-calling it proves it never ran.
        $event->expects($this->never())->method('getUser');

        $model = $this->buildModel(event: $event);

        $model->sendNotificationEmail();
    }

    /**
     * A replace-all has no originating segment — it is applied to every matching segment in the job with
     * nothing open in the editor — so nothing else notifies on its behalf. It accrues no time to edit,
     * but a downgrade it causes must still reach the translator who set the previous status.
     */
    #[Test]
    public function sendNotificationEmail_notifiesOnAReplaceAllLowerTransition(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('isLowerTransition')->willReturn(true);
        $event->expects($this->once())->method('getUser')->willReturn(null);
        $event->method('isAReplaceAllEvent')->willReturn(true);
        $event->method('shouldIncreaseTte')->willReturn(false);

        $model = $this->buildModel(
            isAPropagatedEvent: false,
            event: $event,
            sourcePagesWithFinalRevisions: [2],
        );

        $model->sendNotificationEmail();
    }

    // ─────────────────────────────────────────────────────────────────
    // evaluateChunkReviewEventTransitions — increaseCounters paths
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function evaluateChunkReviewEventTransitions_changingStatusOnThisChunkIncreasesWordCount(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');

        $model = $this->buildModel(
            isChangingStatus: true,
            currentEventOnChunk: true,
            event: $event
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    #[Test]
    public function evaluateChunkReviewEventTransitions_changingStatusWithExistingFinalRevisionRemovesFlag(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setFinalRevisionToRemove')->with(2);
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');

        $model = $this->buildModel(
            isChangingStatus: true,
            currentEventOnChunk: true,
            event: $event,
            sourcePagesWithFinalRevisions: [2],
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    #[Test]
    public function evaluateChunkReviewEventTransitions_modifyRevisionOnSameLevel(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');

        $model = $this->buildModel(
            isChangingStatus: false,
            isIce: false,
            currentEventOnChunk: true,
            event: $event
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    #[Test]
    public function evaluateChunkReviewEventTransitions_modifiedIceOnSameChunkIncreasesWordCount(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');

        $model = $this->buildModel(
            isIce: true,
            isUnModifiedIce: false,
            currentEventOnChunk: true,
            event: $event
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    #[Test]
    public function evaluateChunkReviewEventTransitions_lowerTransitionWithFinalRevisionDecreases(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setFinalRevisionToRemove');
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');
        $event->method('getIssuesToDelete')->willReturn([]);

        $model = $this->buildModel(
            isChangingStatus: true,
            isLowerTransition: true,
            currentEventOnChunk: false,
            event: $event,
            sourcePagesWithFinalRevisions: [2],
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    /**
     * A replace-all edit decrements the counters like any other lower transition, but must leave the
     * final revision in place: the replacement is applied across many segments at once, and removing
     * the final revision for each of them would strip revisions the reviewer never revisited.
     */
    #[Test]
    public function evaluateChunkReviewEventTransitions_lowerTransitionOnAReplaceAllKeepsTheFinalRevision(): void
    {
        $event = $this->createMock(TranslationEvent::class);
        $event->method('isAReplaceAllEvent')->willReturn(true);
        $event->expects($this->never())->method('setFinalRevisionToRemove');
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');
        $event->method('getIssuesToDelete')->willReturn([]);

        $model = $this->buildModel(
            isChangingStatus: true,
            isLowerTransition: true,
            currentEventOnChunk: false,
            event: $event,
            sourcePagesWithFinalRevisions: [2],
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    #[Test]
    public function evaluateChunkReviewEventTransitions_withEmptyChunkReviewsDoesNothing(): void
    {
        $model = $this->buildModel(chunkReviews: []);

        $model->evaluateChunkReviewEventTransitions();
        $this->assertTrue(true);
    }

    // ─────────────────────────────────────────────────────────────────
    // getPenaltyPointsForSourcePage (via decreaseCounters)
    // ─────────────────────────────────────────────────────────────────

    #[Test]
    public function evaluateChunkReviewEventTransitions_lowerTransitionSubtractsIsssuePenaltyPoints(): void
    {
        $issue = $this->createStub(EntryWithCategoryStruct::class);
        $issue->source_page = 2;
        $issue->penalty_points = 5;

        $event = $this->createMock(TranslationEvent::class);
        $event->expects($this->once())->method('setChunkReviewForPassFailUpdate');
        $event->method('getIssuesToDelete')->willReturn([$issue]);

        $model = $this->buildModel(
            isChangingStatus: true,
            isLowerTransition: true,
            currentEventOnChunk: false,
            event: $event,
            sourcePagesWithFinalRevisions: [2],
        );

        $model->evaluateChunkReviewEventTransitions();
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────

    private function buildModel(
        bool $isDraft = false,
        bool $isChangingStatus = false,
        bool $isLowerTransition = false,
        bool $isIce = false,
        bool $isUnModifiedIce = false,
        bool $currentEventOnChunk = false,
        bool $shouldIncreaseTte = false,
        bool $isAPropagatedEvent = false,
        ?TranslationEvent $event = null,
        ?CounterModel $counterModel = null,
        ?SegmentTranslationStruct $wantedTranslation = null,
        ?array $chunkReviews = null,
        ?array $sourcePagesWithFinalRevisions = null,
    ): ReviewedWordCountModel {
        $project = $this->createStub(\Model\Projects\ProjectStruct::class);
        $project->name = 'Test Project';
        $project->id_customer = 'test@example.com';
        $project->id_assignee = null;

        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 99;
        $chunk->password = 'test_password';
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';
        $chunk->method('getProject')->willReturn($project);

        $segment = $this->createStub(SegmentStruct::class);
        $segment->id = 42;
        $segment->raw_word_count = 10;
        $segment->segment = 'Test segment';

        $oldTranslation = $this->createStub(SegmentTranslationStruct::class);
        $oldTranslation->status = 'TRANSLATED';
        $oldTranslation->translation = 'Old translation';
        $oldTranslation->eq_word_count = 5.0;

        if ($wantedTranslation === null) {
            $wantedTranslation = $this->createStub(SegmentTranslationStruct::class);
            $wantedTranslation->status = 'APPROVED';
            $wantedTranslation->translation = 'New translation';
            $wantedTranslation->method('isTranslationStatus')->willReturn(false);
        }

        $translationEventStruct = $this->createStub(TranslationEventStruct::class);
        $translationEventStruct->time_to_edit = 100;
        $translationEventStruct->source_page = 2;

        if ($event === null) {
            $event = $this->createStub(TranslationEvent::class);
        }

        $event->method('getChunk')->willReturn($chunk);
        $event->method('getSegmentStruct')->willReturn($segment);
        $event->method('getOldTranslation')->willReturn($oldTranslation);
        $event->method('getWantedTranslation')->willReturn($wantedTranslation);
        $event->method('getTranslationEventStruct')->willReturn($translationEventStruct);
        $event->method('isADraftChange')->willReturn($isDraft);
        $event->method('isChangingStatus')->willReturn($isChangingStatus);
        $event->method('isLowerTransition')->willReturn($isLowerTransition);
        $event->method('isIce')->willReturn($isIce);
        $event->method('isUnModifiedIce')->willReturn($isUnModifiedIce);
        $event->method('currentEventIsOnThisChunk')->willReturn($currentEventOnChunk);
        $event->method('shouldIncreaseTte')->willReturn($shouldIncreaseTte);
        $event->method('isAPropagatedEvent')->willReturn($isAPropagatedEvent);
        $event->method('getPreviousEventSourcePage')->willReturn(2);
        $event->method('getUser')->willReturn(null);

        if ($chunkReviews === null) {
            $chunkReview = new ChunkReviewStruct();
            $chunkReview->id = 1;
            $chunkReview->id_project = 100;
            $chunkReview->id_job = 99;
            $chunkReview->password = 'job_pass';
            $chunkReview->review_password = 'rev_pass';
            $chunkReview->source_page = 2;
            $chunkReviews = [$chunkReview];
        }

        $model = new ReviewedWordCountModel(
            $event,
            $counterModel ?? $this->createStub(CounterModel::class),
            $chunkReviews,
            $this->dbStub
        );

        if ($sourcePagesWithFinalRevisions !== null) {
            $ref = new \ReflectionProperty($model, '_sourcePagesWithFinalRevisions');
            $ref->setValue($model, $sourcePagesWithFinalRevisions);
        }

        return $model;
    }
}
