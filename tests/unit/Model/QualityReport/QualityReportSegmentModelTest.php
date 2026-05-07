<?php

declare(strict_types=1);

namespace unit\Model\QualityReport;

use Matecat\SubFiltering\MateCatFilter;
use Model\Comments\BaseCommentStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\LQA\ChunkReviewDao;
use Model\QualityReport\QualityReportSegmentModel;
use Model\QualityReport\QualityReportSegmentStruct;
use Model\QualityReport\SegmentEventsStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
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
        $metadataDao = $this->createStub(MetadataDao::class);
        $segment = new QualityReportSegmentStruct([], $metadataDao);

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

    #[Test]
    public function ConstructStoresChunk(): void
    {
        $chunk = $this->createChunk();
        $model = new TestableQualityReportSegmentModel($chunk, null);

        $property = new ReflectionProperty(QualityReportSegmentModel::class, 'chunk');

        $this->assertSame($chunk, $property->getValue($model));
    }

    #[Test]
    public function ParentGetSegmentsForQRReturnsEmptyWhenChunkCredentialsMissing(): void
    {
        $model = new QualityReportSegmentModel($this->createChunk(null, null), null);

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

        $model = new QualityReportSegmentModel($chunk, null);

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
        $model = new QualityReportSegmentModel($this->createChunk(), null);

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

        $model = new QualityReportSegmentModel($this->createChunk(), $mockChunkReviewDao);

        $method = new ReflectionMethod(QualityReportSegmentModel::class, '_getChunkReviews');
        
        $result = $method->invoke($model);

        $this->assertCount(1, $result);
        $this->assertSame(3, $result[0]->source_page);
    }

    #[Test]
    public function AssignIssuesAddsOnlyMatchingSegmentAndAttachesCommentsAndRevisionNumber(): void
    {
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
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
        $model = new TestableQualityReportSegmentModel($this->createChunk(), null);
        $match = $this->createEvent(10, SourcePages::SOURCE_PAGE_REVISION, 'rev');
        $events = [
            $this->createEvent(10, SourcePages::SOURCE_PAGE_TRANSLATE, 'translate'),
            $match,
            $this->createEvent(20, SourcePages::SOURCE_PAGE_REVISION, 'other'),
        ];

        $this->assertSame($match, $model->invokeProtected('filterEvent', [10, SourcePages::SOURCE_PAGE_REVISION, $events]));
        $this->assertNull($model->invokeProtected('filterEvent', [10, SourcePages::SOURCE_PAGE_REVISION_2, $events]));
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
