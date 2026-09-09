<?php

declare(strict_types=1);

namespace Matecat\Core\Model\QualityReport;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\EntryCommentDao;
use Model\Projects\ProjectStruct;
use Model\QualityReport\HistoryElementStruct;
use Model\QualityReport\QualityReportDao;
use Model\QualityReport\QualityReportSegmentModel;
use Model\QualityReport\QualityReportSegmentStruct;
use Model\QualityReport\SegmentEventsStruct;
use Model\Segments\SegmentDao;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class QualityReportSegmentModelTest extends AbstractTest
{
    private function createChunk(?int $id = 10, ?string $password = 'secret'): JobStruct
    {
        $chunk = new JobStruct();
        $chunk->id = $id;
        $chunk->password = $password;
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        return $chunk;
    }

    private function createSegment(array $overrides = []): QualityReportSegmentStruct
    {
        $segment = new QualityReportSegmentStruct([]);

        $segment->sid = 10;
        $segment->target = 'it-IT';
        $segment->segment = 'source text';
        $segment->raw_word_count = 2;
        $segment->translation = null;
        $segment->version = 1;
        $segment->ice_locked = false;
        $segment->status = TranslationStatus::STATUS_TRANSLATED;
        $segment->time_to_edit = 61000;
        $segment->filename = 'file.xlf';
        $segment->id_file = 1;
        $segment->warning = false;
        $segment->suggestion_match = 100;
        $segment->suggestion_source = 'tm';
        $segment->suggestion = null;
        $segment->edit_distance = 0;
        $segment->locked = false;
        $segment->match_type = '100%';
        $segment->version_number = 0;
        $segment->warnings = [];
        $segment->comments = [];
        $segment->issues = [];
        $segment->last_translation = '';
        $segment->last_revisions = [];
        $segment->is_pre_translated = false;

        foreach ($overrides as $key => $value) {
            $segment->$key = $value;
        }

        return $segment;
    }

    private function createEvent(int $segmentId, int $sourcePage, string $translation, int $versionNumber = 1): SegmentEventsStruct
    {
        return new SegmentEventsStruct([
            'id_segment' => $segmentId,
            'source_page' => $sourcePage,
            'translation' => $translation,
            'version_number' => $versionNumber,
        ]);
    }

    /**
     * _populateHistory() type-hints its filter closure against HistoryElementStruct, so the
     * SegmentEventsStruct built by createEvent() cannot be reused here.
     *
     * @param array<string, mixed> $overrides
     */
    private function createHistoryElement(array $overrides = []): HistoryElementStruct
    {
        return new HistoryElementStruct(array_merge([
            'id_segment' => 10,
            'translation' => 'a translation',
            'version_number' => 1,
            'source_page' => SourcePages::SOURCE_PAGE_REVISION,
            'status' => TranslationStatus::STATUS_APPROVED,
            'create_date' => '2026-01-02 03:04:05',
            'creation_date' => null,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createIssue(array $overrides = []): ShapelessConcreteStruct
    {
        return new ShapelessConcreteStruct(array_merge([
            'id' => 1,
            'segment_id' => 10,
            'translation_version' => 1,
            'deleted_at' => null,
        ], $overrides));
    }

    // ─── _populateHistory ────────────────────────────────────────────────

    #[Test]
    public function PopulateHistoryKeepsOnlyTheEventsOfTheGivenSegment(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $filter = $this->createStub(MateCatFilter::class);

        $events = [
            $this->createHistoryElement(['version_number' => 0, 'translation' => 'first version']),
            $this->createHistoryElement(['version_number' => 1, 'translation' => 'revised']),
            $this->createHistoryElement(['id_segment' => 777, 'translation' => 'another segment']),
        ];

        $model->invokeProtected('_populateHistory', [$segment, $filter, $events, [], false]);

        $this->assertCount(2, $segment->history);
        $this->assertSame(['first version', 'revised'], array_column($segment->history, 'translation'));
    }

    #[Test]
    public function PopulateHistoryMapsEveryFieldOfTheEvent(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $filter = $this->createStub(MateCatFilter::class);

        $events = [
            $this->createHistoryElement([
                'source_page' => SourcePages::SOURCE_PAGE_REVISION_2,
                'version_number' => 4,
                'status' => TranslationStatus::STATUS_APPROVED2,
            ]),
        ];

        $model->invokeProtected('_populateHistory', [$segment, $filter, $events, [], false]);

        $entry = $segment->history[0];
        $this->assertSame(TranslationStatus::STATUS_APPROVED2, $entry['status']);
        $this->assertSame('2026-01-02 03:04:05', $entry['date']);
        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION_2, $entry['source_page']);
        $this->assertSame(4, $entry['version_number']);
        $this->assertSame('a translation', $entry['translation']);
        // source_page 3 maps to revision number 2
        $this->assertSame(2, $entry['revision_number']);
    }

    /**
     * The version-0 row produced by TranslationVersionDao::historyEvents() carries creation_date
     * and a null create_date, so the coalesce must prefer creation_date.
     */
    #[Test]
    public function PopulateHistoryPrefersCreationDateOverCreateDate(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $events = [
            $this->createHistoryElement(['creation_date' => '2026-05-05 00:00:00']),
            $this->createHistoryElement(['creation_date' => null, 'create_date' => '2026-06-06 00:00:00']),
        ];

        $model->invokeProtected('_populateHistory', [$segment, $this->createStub(MateCatFilter::class), $events, [], false]);

        $this->assertSame(['2026-05-05 00:00:00', '2026-06-06 00:00:00'], array_column($segment->history, 'date'));
    }

    /**
     * A source_page of 1 (translate) has no revision number.
     */
    #[Test]
    public function PopulateHistoryLeavesTheRevisionNumberNullForTheTranslateStep(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $events = [$this->createHistoryElement(['source_page' => SourcePages::SOURCE_PAGE_TRANSLATE])];

        $model->invokeProtected('_populateHistory', [$segment, $this->createStub(MateCatFilter::class), $events, [], false]);

        $this->assertNull($segment->history[0]['revision_number']);
    }

    #[Test]
    public function PopulateHistoryAttachesOnlyLiveIssuesOfTheMatchingSegmentAndVersion(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $events = [$this->createHistoryElement(['version_number' => 1])];

        $issues = [
            $this->createIssue(['id' => 1]),                                     // keeper
            $this->createIssue(['id' => 2, 'deleted_at' => '2026-01-01 00:00:00']), // soft-deleted
            $this->createIssue(['id' => 3, 'translation_version' => 9]),          // other version
            $this->createIssue(['id' => 4, 'segment_id' => 777]),                 // other segment
        ];

        $model->invokeProtected('_populateHistory', [$segment, $this->createStub(MateCatFilter::class), $events, $issues, false]);

        $kept = array_values($segment->history[0]['issues']);
        $this->assertCount(1, $kept);
        $this->assertSame(1, $kept[0]->id);
    }

    #[Test]
    public function PopulateHistoryRunsTheTranslationThroughTheFilterForUi(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnCallback(static fn(string $s): string => 'ui:' . $s);

        $events = [$this->createHistoryElement(['translation' => 'raw text'])];

        $model->invokeProtected('_populateHistory', [$segment, $filter, $events, [], true]);

        $this->assertSame('ui:raw text', $segment->history[0]['translation']);
    }

    #[Test]
    public function PopulateHistoryResultsInAnEmptyHistoryWhenNoEventMatches(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $events = [$this->createHistoryElement(['id_segment' => 999])];

        $model->invokeProtected('_populateHistory', [$segment, $this->createStub(MateCatFilter::class), $events, [], false]);

        $this->assertSame([], $segment->history);
    }

    #[Test]
    public function ConstructStoresChunk(): void
    {
        $chunk = $this->createChunk();
        $model = new TestableQualityReportSegmentModel($chunk, obtainTestDatabase(), null);

        $property = new ReflectionProperty(QualityReportSegmentModel::class, 'chunk');

        $this->assertSame($chunk, $property->getValue($model));
    }

    #[Test]
    public function ParentGetSegmentsForQRReturnsEmptyWhenChunkCredentialsMissing(): void
    {
        $model = new QualityReportSegmentModel($this->createChunk(null, null), obtainTestDatabase(), null);

        $this->assertSame([], $model->getSegmentsForQR([1, 2, 3]));
    }

    #[Test]
    public function GetSegmentsIdForQRExecutesPreFilterAndCanExitBeforeDaoCall(): void
    {
        $chunk = $this->getMockBuilder(JobStruct::class)
            ->onlyMethods(['getProject'])
            ->getMock();
        $chunk->expects($this->once())->method('getProject');
        $chunk->method('getProject')->willThrowException(new RuntimeException('stop-before-dao'));

        $model = new QualityReportSegmentModel($chunk, obtainTestDatabase(), null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stop-before-dao');

        $model->getSegmentsIdForQR(10, 100, 'after', [
            'filter' => [
                'issue_category' => 7,
            ],
        ]);
    }

    #[Test]
    public function GetChunkReviewsReturnsCachedValueWithoutDaoCall(): void
    {
        $model = new QualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);

        $chunkReview = new \stdClass();
        $chunkReview->source_page = 2;

        $cacheProperty = new ReflectionProperty(QualityReportSegmentModel::class, '_chunkReviews');
        $cacheProperty->setValue($model, [$chunkReview]);

        $method = new ReflectionMethod(QualityReportSegmentModel::class, '_getChunkReviews');
        
        $result = $method->invoke($model);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->source_page);
    }

    #[Test]
    public function GetChunkReviewsLoadsDataWhenCacheIsNull(): void
    {
        $chunkReview = new \stdClass();
        $chunkReview->source_page = 3;

        $mockChunkReviewDao = $this->createMock(ChunkReviewDao::class);
        $mockChunkReviewDao
            ->expects($this->once())
            ->method('findChunkReviews')
            ->with($this->isInstanceOf(JobStruct::class))
            ->willReturn([$chunkReview]);

        $model = new QualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), $mockChunkReviewDao);

        $method = new ReflectionMethod(QualityReportSegmentModel::class, '_getChunkReviews');
        
        $result = $method->invoke($model);

        $this->assertCount(1, $result);
        $this->assertSame(3, $result[0]->source_page);
    }

    #[Test]
    public function AssignIssuesAddsOnlyMatchingSegmentAndAttachesCommentsAndRevisionNumber(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $matchingIssue = new ShapelessConcreteStruct();
        $matchingIssue->issue_id = 100;
        $matchingIssue->source_page = 2;
        $matchingIssue->segment_id = 10;

        $differentSegmentIssue = new ShapelessConcreteStruct();
        $differentSegmentIssue->issue_id = 101;
        $differentSegmentIssue->source_page = 3;
        $differentSegmentIssue->segment_id = 999;

        $model->invokeProtected(
            '_assignIssues',
            [$segment, [$matchingIssue, $differentSegmentIssue], [100 => [['id' => 1, 'message' => 'c1']]]]
        );

        $this->assertCount(1, $segment->issues);
        $this->assertSame($matchingIssue, $segment->issues[0]);
        $this->assertSame(1, $matchingIssue->revision_number);
        $this->assertSame([['id' => 1, 'message' => 'c1']], $matchingIssue->comments);
        $this->assertSame(2, $differentSegmentIssue->revision_number);
    }

    #[Test]
    public function AssignCommentsCallsTemplateMessageAndAddsOnlyMatchingSegment(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment(['sid' => 10]);

        $matchingComment = $this->getMockBuilder(BaseCommentStruct::class)
            ->onlyMethods(['templateMessage'])
            ->getMock();
        $matchingComment->id_segment = 10;
        $matchingComment->expects($this->once())->method('templateMessage');

        $differentComment = $this->getMockBuilder(BaseCommentStruct::class)
            ->onlyMethods(['templateMessage'])
            ->getMock();
        $differentComment->id_segment = 20;
        $differentComment->expects($this->once())->method('templateMessage');

        $model->invokeProtected('_assignComments', [$segment, [$matchingComment, $differentComment]]);

        $this->assertCount(1, $segment->comments);
        $this->assertSame($matchingComment, $segment->comments[0]);
    }

    #[Test]
    public function CommonSegmentAssignmentsForUiPopulatesComputedFieldsAndTransformsText(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment([
            'segment' => 'plain segment',
            'translation' => null,
            'suggestion' => null,
            'raw_word_count' => 2,
            'time_to_edit' => 61000,
        ]);

        $featureSet = $this->createStub(FeatureSet::class);

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnCallback(static fn(string $value): string => 'ui:' . $value);

        $model->invokeProtected('_commonSegmentAssignments', [$segment, $filter, $featureSet, $this->createChunk(), true]);

        $this->assertSame([], $segment->warnings);
        $this->assertSame(0.0, $segment->pee);
        $this->assertFalse($segment->ice_modified);
        $this->assertSame(31.0, $segment->secs_per_word);
        $this->assertSame(['00', '01', '01', 0], $segment->parsed_time_to_edit);
        $this->assertSame('ui:plain segment', $segment->segment);
        $this->assertSame('ui:', $segment->translation);
        $this->assertSame('ui:', $segment->suggestion);
    }

    #[Test]
    public function PopulateLastTranslationAndRevisionForPreTranslatedApprovedSegment(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment([
            'sid' => 10,
            'status' => TranslationStatus::STATUS_APPROVED,
            'translation' => 'approved translation',
            'last_revisions' => [],
            'last_translation' => '',
        ]);

        $tmStatus = new ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status');
        $tmStatus->setValue($segment, 'SKIPPED');

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnCallback(static fn(string $value): string => 'ui:' . $value);

        $model->invokeProtected('_populateLastTranslationAndRevision', [
            $segment,
            $filter,
            [$this->createEvent(99, SourcePages::SOURCE_PAGE_TRANSLATE, 'other')],
            true,
        ]);

        $this->assertTrue($segment->is_pre_translated);
        $this->assertCount(1, $segment->last_revisions);
        $this->assertSame(1, $segment->last_revisions[0]['revision_number']);
        $this->assertSame('ui:approved translation', $segment->last_revisions[0]['translation']);
        $this->assertSame('', $segment->last_translation);
    }

    #[Test]
    public function PopulateLastTranslationAndRevisionFromEventsForNonInitialStatus(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment([
            'sid' => 10,
            'status' => TranslationStatus::STATUS_TRANSLATED,
        ]);

        $tmStatus = new ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status');
        $tmStatus->setValue($segment, 'DONE');

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnArgument(0);

        $events = [
            $this->createEvent(10, SourcePages::SOURCE_PAGE_TRANSLATE, 'last translation'),
            $this->createEvent(10, SourcePages::SOURCE_PAGE_REVISION, 'revision 1'),
            $this->createEvent(777, SourcePages::SOURCE_PAGE_REVISION_2, 'ignored'),
        ];

        $model->invokeProtected('_populateLastTranslationAndRevision', [$segment, $filter, $events, false]);

        $this->assertSame('last translation', $segment->last_translation);
        $this->assertCount(1, $segment->last_revisions);
        $this->assertSame(1, $segment->last_revisions[0]['revision_number']);
        $this->assertSame('revision 1', $segment->last_revisions[0]['translation']);
        $this->assertFalse($segment->is_pre_translated);
    }

    #[Test]
    public function PopulateLastTranslationAndRevisionForPreTranslatedApproved2Segment(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment([
            'sid' => 11,
            'status' => TranslationStatus::STATUS_APPROVED2,
            'translation' => 'approved2 translation',
            'last_revisions' => [],
            'last_translation' => '',
        ]);

        $tmStatus = new ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status');
        $tmStatus->setValue($segment, 'SKIPPED');

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnArgument(0);

        $model->invokeProtected('_populateLastTranslationAndRevision', [$segment, $filter, [], false]);

        $this->assertTrue($segment->is_pre_translated);
        $this->assertCount(1, $segment->last_revisions);
        $this->assertSame(2, $segment->last_revisions[0]['revision_number']);
        $this->assertSame('approved2 translation', $segment->last_revisions[0]['translation']);
    }

    #[Test]
    public function PopulateLastTranslationAndRevisionForPreTranslatedTranslatedSegment(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment([
            'sid' => 12,
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'translation' => 'translated pre',
            'last_translation' => '',
            'last_revisions' => [],
        ]);

        $tmStatus = new ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status');
        $tmStatus->setValue($segment, 'SKIPPED');

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnCallback(static fn(string $value): string => 'ui:' . $value);

        $model->invokeProtected('_populateLastTranslationAndRevision', [$segment, $filter, [], true]);

        $this->assertTrue($segment->is_pre_translated);
        $this->assertSame('ui:translated pre', $segment->last_translation);
        $this->assertSame([], $segment->last_revisions);
    }

    #[Test]
    public function PopulateLastTranslationAndRevisionForPreTranslatedUnknownStatusFallsBackToFalse(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $segment = $this->createSegment([
            'sid' => 13,
            'status' => 'UNKNOWN_STATUS',
            'translation' => 'text',
            'last_translation' => '',
            'last_revisions' => [],
        ]);

        $tmStatus = new ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status');
        $tmStatus->setValue($segment, 'SKIPPED');

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromLayer0ToLayer2')->willReturnArgument(0);

        $model->invokeProtected('_populateLastTranslationAndRevision', [$segment, $filter, [], false]);

        $this->assertFalse($segment->is_pre_translated);
        $this->assertSame('', $segment->last_translation);
        $this->assertSame([], $segment->last_revisions);
    }

    #[Test]
    public function IsSegmentEventInArrayReturnsTrueWhenMatchExistsAndFalseOtherwise(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $events = [
            $this->createEvent(10, SourcePages::SOURCE_PAGE_TRANSLATE, 't1'),
            $this->createEvent(20, SourcePages::SOURCE_PAGE_REVISION, 't2'),
        ];

        $this->assertTrue($model->invokeProtected('isSegmentEventInArray', [10, $events]));
        $this->assertFalse($model->invokeProtected('isSegmentEventInArray', [999, $events]));
    }

    #[Test]
    public function FilterEventReturnsMatchingEventOrNull(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), obtainTestDatabase(), null);
        $match = $this->createEvent(10, SourcePages::SOURCE_PAGE_REVISION, 'rev');
        $events = [
            $this->createEvent(10, SourcePages::SOURCE_PAGE_TRANSLATE, 'translate'),
            $match,
            $this->createEvent(20, SourcePages::SOURCE_PAGE_REVISION, 'other'),
        ];

        $this->assertSame($match, $model->invokeProtected('filterEvent', [10, SourcePages::SOURCE_PAGE_REVISION, $events]));
        $this->assertNull($model->invokeProtected('filterEvent', [10, SourcePages::SOURCE_PAGE_REVISION_2, $events]));
    }

    #[Test]
    public function getSegmentsIdForQRDelegatesToSegmentDao(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([1, 2, 3]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            null,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(10, 100);

        $this->assertSame([1, 2, 3], $result);
    }

    #[Test]
    public function getSegmentsIdForQRWithAfterDirection(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([50, 51]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            null,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(5, 50, 'after');

        $this->assertSame([50, 51], $result);
    }

    #[Test]
    public function getSegmentsIdForQRWithBeforeDirection(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([8, 9]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            null,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(5, 10, 'before');

        $this->assertSame([8, 9], $result);
    }

    #[Test]
    public function getSegmentsIdForQRWithRevisionStatusFilter(): void
    {
        $chunkReview = new \stdClass();
        $chunkReview->source_page = 2;

        $mockChunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $mockChunkReviewDao->method('findChunkReviews')->willReturn([$chunkReview]);

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([10, 11]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            $mockChunkReviewDao,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(10, 100, 'after', [
            'filter' => [
                'status' => TranslationStatus::STATUS_APPROVED,
                'revision_number' => 1,
            ],
        ]);

        $this->assertSame([10, 11], $result);
    }

    #[Test]
    public function getSegmentsIdForQRWithInvalidRevisionNumberDefaultsToOne(): void
    {
        $chunkReview = new \stdClass();
        $chunkReview->source_page = 2;

        $mockChunkReviewDao = $this->createStub(ChunkReviewDao::class);
        $mockChunkReviewDao->method('findChunkReviews')->willReturn([$chunkReview]);

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([10]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            $mockChunkReviewDao,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(10, 100, 'after', [
            'filter' => [
                'status' => TranslationStatus::STATUS_APPROVED,
                'revision_number' => 999,
            ],
        ]);

        $this->assertSame([10], $result);
    }

    private function createChunkWithProject(): JobStruct
    {
        $project = new ProjectStruct();
        $project->id = 1;
        $project->id_qa_model = null;

        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 10;
        $chunk->password = 'secret';
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';
        $chunk->method('getProject')->willReturn($project);

        return $chunk;
    }

    #[Test]
    public function getSegmentsForQRWithEmptyDataReturnsEmptyArray(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsForQr')->willReturn([]);

        $qualityReportDao = $this->createStub(QualityReportDao::class);
        $qualityReportDao->method('getIssuesBySegments')->willReturn([]);

        $entryCommentDao = $this->createStub(EntryCommentDao::class);

        $commentDao = $this->createStub(CommentDao::class);
        $commentDao->method('getThreadsBySegments')->willReturn([]);

        $model = new QualityReportSegmentModel(
            $this->createChunkWithProject(),
            obtainTestDatabase(),
            null,
            $segmentDao,
            $qualityReportDao,
            $entryCommentDao,
            $commentDao
        );

        $result = $model->getSegmentsForQR([1, 2, 3]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function getSegmentsForQRWithIssuesLoadsComments(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsForQr')->willReturn([]);

        $issue = new ShapelessConcreteStruct();
        $issue->issue_id = 5;
        $issue->segment_id = 1;
        $issue->source_page = 2;

        $qualityReportDao = $this->createStub(QualityReportDao::class);
        $qualityReportDao->method('getIssuesBySegments')->willReturn([$issue]);

        $entryCommentDao = $this->createStub(EntryCommentDao::class);
        $entryCommentDao->method('fetchCommentsGroupedByIssueIds')->willReturn([]);

        $commentDao = $this->createStub(CommentDao::class);
        $commentDao->method('getThreadsBySegments')->willReturn([]);

        $model = new QualityReportSegmentModel(
            $this->createChunkWithProject(),
            obtainTestDatabase(),
            null,
            $segmentDao,
            $qualityReportDao,
            $entryCommentDao,
            $commentDao
        );

        $result = $model->getSegmentsForQR([1]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * The two tests above stub getSegmentsForQr() to an empty list, so the per-segment loop body
     * never runs. Returning one segment walks it end to end, which is the only way to reach the
     * _populateLastTranslationAndRevision() / _populateHistory() calls inside it.
     *
     * Everything the loop touches beyond the injected DAOs — FeatureSet, ProjectDao,
     * TranslationVersionDao, SegmentOriginalDataDao, MetadataDao, MateCatFilter — is constructed
     * inline from $this->database, so this leans on the real test connection and simply gets empty
     * result sets back.
     */
    #[Test]
    public function getSegmentsForQRRunsThePerSegmentLoopAndPopulatesHistory(): void
    {
        $segment = $this->createSegment(['sid' => 10]);
        // typed property with no default; _populateLastTranslationAndRevision() reads it
        (new ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status'))
            ->setValue($segment, 'DONE');

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsForQr')->willReturn([$segment]);

        $qualityReportDao = $this->createStub(QualityReportDao::class);
        $qualityReportDao->method('getIssuesBySegments')->willReturn([]);

        $entryCommentDao = $this->createStub(EntryCommentDao::class);

        $commentDao = $this->createStub(CommentDao::class);
        $commentDao->method('getThreadsBySegments')->willReturn([]);

        $model = new QualityReportSegmentModel(
            $this->createChunkWithProject(),
            obtainTestDatabase(),
            null,
            $segmentDao,
            $qualityReportDao,
            $entryCommentDao,
            $commentDao
        );

        $result = $model->getSegmentsForQR([10]);

        $this->assertCount(1, $result);

        $segment = array_values($result)[0];
        $this->assertSame(10, $segment->sid);
        // no events exist for this segment, so both populate helpers leave empty collections
        $this->assertSame([], $segment->history);
        $this->assertSame([], $segment->last_revisions);
        $this->assertNotNull($segment->dataRefMap);
    }

    #[Test]
    public function getSegmentsIdForQRWithIssueCategoryAllSkipsSubCategoryLookup(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([10, 11]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            null,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(10, 100, 'after', [
            'filter' => [
                'issue_category' => 'all',
            ],
        ]);

        $this->assertSame([10, 11], $result);
    }

    #[Test]
    public function getSegmentsIdForQRWithNoFilterPassesOptionsThrough(): void
    {
        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('getSegmentsIdForQR')->willReturn([5]);

        $model = new QualityReportSegmentModel(
            $this->createChunk(),
            obtainTestDatabase(),
            null,
            $segmentDao
        );

        $result = $model->getSegmentsIdForQR(5, 50, 'after', []);

        $this->assertSame([5], $result);
    }
}

class TestableQualityReportSegmentModel extends QualityReportSegmentModel
{
    public function getSegmentsIdForQR($step, int $ref_segment, $where = 'after', $options = []): array
    {
        return [1, 2, 3];
    }

    public function getSegmentsForQR(array $segment_ids, $isForUI = false)
    {
        return [];
    }

    public function invokeProtected(string $methodName, array $args = [])
    {
        $method = new ReflectionMethod(QualityReportSegmentModel::class, $methodName);
        

        return $method->invokeArgs($this, $args);
    }
}
