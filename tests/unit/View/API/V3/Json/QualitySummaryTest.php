<?php

namespace Tests\unit\View\API\V3\Json;

use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectStruct;
use Model\QualityReport\QualityReportDao;
use Model\ReviseFeedback\FeedbackDAO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plugins\Features\ReviewExtended\IChunkReviewModel;
use Plugins\Features\RevisionFactory;
use RuntimeException;
use View\API\V3\Json\QualitySummary;

/**
 * Testable subclass — overrides protected factory methods so no DB or
 * RevisionFactory is needed.
 */
class TestableQualitySummary extends QualitySummary
{
    private QualityReportDao $qualityReportDaoOverride;
    private FeedbackDAO $feedbackDaoOverride;
    private EntryDao $entryDaoOverride;
    /** @var list<ShapelessConcreteStruct> */
    private array $filePartsOverride = [];
    private RevisionFactory $revisionFeatureOverride;

    public function setQualityReportDao(QualityReportDao $dao): void
    {
        $this->qualityReportDaoOverride = $dao;
    }

    public function setFeedbackDao(FeedbackDAO $dao): void
    {
        $this->feedbackDaoOverride = $dao;
    }

    public function setEntryDao(EntryDao $dao): void
    {
        $this->entryDaoOverride = $dao;
    }

    /** @param list<ShapelessConcreteStruct> $parts */
    public function setFileParts(array $parts): void
    {
        $this->filePartsOverride = $parts;
    }

    public function setRevisionFeature(RevisionFactory $factory): void
    {
        $this->revisionFeatureOverride = $factory;
    }

    protected function createQualityReportDao(): QualityReportDao
    {
        return $this->qualityReportDaoOverride;
    }

    protected function createFeedbackDao(): FeedbackDAO
    {
        return $this->feedbackDaoOverride;
    }

    protected function createEntryDao(): EntryDao
    {
        return $this->entryDaoOverride;
    }

    protected function getReviewedWordsCountGroupedByFileParts(int $idJob, string $password, int $revisionNumber): array
    {
        return $this->filePartsOverride;
    }

    protected function createRevisionFeature(ProjectStruct $project): RevisionFactory
    {
        return $this->revisionFeatureOverride;
    }
}

#[CoversClass(QualitySummary::class)]
class QualitySummaryTest extends TestCase
{
    private JobStruct $chunk;
    private ProjectStruct $project;
    private TestableQualitySummary $sut;

    protected function setUp(): void
    {
        $this->chunk   = new JobStruct();
        $this->chunk->id       = 100;
        $this->chunk->password = 'abc123';

        $this->project = new ProjectStruct();

        $this->sut = new TestableQualitySummary($this->chunk, $this->project);

        $qrDao = $this->createStub(QualityReportDao::class);
        $qrDao->method('getReviseIssuesByChunk')->willReturn([]);
        $this->sut->setQualityReportDao($qrDao);

        $feedbackDao = $this->createStub(FeedbackDAO::class);
        $feedbackDao->method('getFeedback')->willReturn(null);
        $this->sut->setFeedbackDao($feedbackDao);

        $entryDao = $this->createStub(EntryDao::class);
        $entryDao->method('getIssuesGroupedByIdFilePart')->willReturn([]);
        $this->sut->setEntryDao($entryDao);

        $this->sut->setFileParts([]);

        $chunkReviewModel = $this->createStub(IChunkReviewModel::class);
        $chunkReviewModel->method('getScore')->willReturn(0.0);
        $chunkReviewModel->method('getPenaltyPoints')->willReturn(0.0);
        $chunkReviewModel->method('getReviewedWordsCount')->willReturn(0);
        $chunkReviewModel->method('getQALimit')->willReturn(100);

        $revisionFactory = $this->createStub(RevisionFactory::class);
        $revisionFactory->method('getChunkReviewModel')->willReturn($chunkReviewModel);
        $this->sut->setRevisionFeature($revisionFactory);
    }

    // ---------------------------------------------------------------
    // render()
    // ---------------------------------------------------------------

    #[Test]
    public function render_empty_list_returns_empty_quality_summary(): void
    {
        $result = $this->sut->render([]);

        self::assertArrayHasKey('quality_summary', $result);
        self::assertSame([], $result['quality_summary']);
    }

    #[Test]
    public function render_single_review_returns_one_item(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page     = 2;
        $review->is_pass         = true;
        $review->total_tte       = 120;
        $review->review_password = null;

        $result = $this->sut->render([$review]);

        self::assertCount(1, $result['quality_summary']);
        $item = $result['quality_summary'][0];
        self::assertSame(1, $item['revision_number']);
        self::assertSame('excellent', $item['quality_overall']);
        self::assertTrue($item['is_pass']);
    }

    #[Test]
    public function render_multiple_reviews_returns_matching_count(): void
    {
        $r1 = new ChunkReviewStruct();
        $r1->source_page = 2;
        $r1->is_pass     = true;
        $r1->total_tte   = 0;

        $r2 = new ChunkReviewStruct();
        $r2->source_page = 3;
        $r2->is_pass     = false;
        $r2->total_tte   = 0;

        $result = $this->sut->render([$r1, $r2]);

        self::assertCount(2, $result['quality_summary']);
    }

    // ---------------------------------------------------------------
    // quality_overall / is_pass logic
    // ---------------------------------------------------------------

    #[Test]
    public function is_pass_null_yields_null_quality_overall(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = null;
        $review->total_tte   = 0;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertNull($item['quality_overall']);
        self::assertNull($item['is_pass']);
    }

    #[Test]
    public function is_pass_false_yields_fail_quality_overall(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = false;
        $review->total_tte   = 0;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertSame('fail', $item['quality_overall']);
        self::assertFalse($item['is_pass']);
    }

    #[Test]
    public function is_pass_true_yields_excellent_quality_overall(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertSame('excellent', $item['quality_overall']);
        self::assertTrue($item['is_pass']);
    }

    // ---------------------------------------------------------------
    // reviseIssues aggregation
    // ---------------------------------------------------------------

    #[Test]
    public function revise_issues_aggregated_correctly(): void
    {
        $issue1 = new ShapelessConcreteStruct();
        $issue1->id_category          = 42;
        $issue1->issue_category_label = 'Accuracy';
        $issue1->issue_severity       = 'minor';

        $issue2 = new ShapelessConcreteStruct();
        $issue2->id_category          = 42;
        $issue2->issue_category_label = 'Accuracy';
        $issue2->issue_severity       = 'minor';

        $issue3 = new ShapelessConcreteStruct();
        $issue3->id_category          = 42;
        $issue3->issue_category_label = 'Accuracy';
        $issue3->issue_severity       = 'major';

        $qrDao = $this->createStub(QualityReportDao::class);
        $qrDao->method('getReviseIssuesByChunk')->willReturn([$issue1, $issue2, $issue3]);
        $this->sut->setQualityReportDao($qrDao);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result      = $this->sut->render([$review]);
        $reviseIssues = $result['quality_summary'][0]['revise_issues'];

        self::assertArrayHasKey(42, $reviseIssues);
        self::assertSame('Accuracy', $reviseIssues[42]['name']);
        self::assertSame(2, $reviseIssues[42]['founds']['minor']);
        self::assertSame(1, $reviseIssues[42]['founds']['major']);
    }

    // ---------------------------------------------------------------
    // model fields
    // ---------------------------------------------------------------

    #[Test]
    public function model_fields_null_when_no_lqa_model(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertNull($item['model_version']);
        self::assertNull($item['model_id']);
        self::assertNull($item['model_label']);
        self::assertNull($item['model_template_id']);
        self::assertTrue($item['passfail']);
    }

    // ---------------------------------------------------------------
    // feedback
    // ---------------------------------------------------------------

    #[Test]
    public function feedback_returned_when_review_password_present(): void
    {
        $feedbackResult = new ShapelessConcreteStruct();
        $feedbackResult['feedback'] = 'Good job';

        $feedbackDao = $this->createStub(FeedbackDAO::class);
        $feedbackDao->method('getFeedback')->willReturn($feedbackResult);
        $this->sut->setFeedbackDao($feedbackDao);

        $review = new ChunkReviewStruct();
        $review->source_page     = 2;
        $review->is_pass         = true;
        $review->total_tte       = 0;
        $review->review_password = 'rev_pass_123';

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertSame('Good job', $item['feedback']);
    }

    #[Test]
    public function feedback_null_when_no_review_password(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page     = 2;
        $review->is_pass         = true;
        $review->total_tte       = 0;
        $review->review_password = null;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertNull($item['feedback']);
    }

    // ---------------------------------------------------------------
    // total_time_to_edit
    // ---------------------------------------------------------------

    #[Test]
    public function total_time_to_edit_cast_to_int(): void
    {
        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 999;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertSame(999, $item['total_time_to_edit']);
    }

    // ---------------------------------------------------------------
    // details (getDetails)
    // ---------------------------------------------------------------

    #[Test]
    public function details_populated_from_file_parts(): void
    {
        $filePart = new ShapelessConcreteStruct();
        $filePart->id_file                         = '10';
        $filePart->id_file_part                    = '20';
        $filePart->filename                        = 'test.xliff';
        $filePart->id_file_part_external_reference = null;
        $filePart->tag_key                         = null;
        $filePart->tag_value                       = null;
        $filePart->reviewed_words_count            = '150.5';

        $this->sut->setFileParts([$filePart]);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result  = $this->sut->render([$review]);
        $details = $result['quality_summary'][0]['details'];

        self::assertCount(1, $details);
        self::assertSame(10, $details[0]['id_file']);
        self::assertSame(20, $details[0]['id_file_part']);
        self::assertSame('test.xliff', $details[0]['original_filename']);
        self::assertSame(150.5, $details[0]['reviewed_words_count']);
        self::assertSame(0.0, $details[0]['issues_weight']);
        self::assertSame(0, $details[0]['issues_entries']);
        self::assertSame([], $details[0]['issues']);
    }

    #[Test]
    public function details_uses_tag_value_as_original_filename_when_external_ref(): void
    {
        $filePart = new ShapelessConcreteStruct();
        $filePart->id_file                         = '10';
        $filePart->id_file_part                    = '20';
        $filePart->filename                        = 'internal.xliff';
        $filePart->id_file_part_external_reference = 999;
        $filePart->tag_key                         = 'original';
        $filePart->tag_value                       = 'customer_original.docx';
        $filePart->reviewed_words_count            = '100';

        $this->sut->setFileParts([$filePart]);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result  = $this->sut->render([$review]);
        $details = $result['quality_summary'][0]['details'];

        self::assertSame('customer_original.docx', $details[0]['original_filename']);
    }

    #[Test]
    public function details_includes_issues_with_penalty_points(): void
    {
        $filePart = new ShapelessConcreteStruct();
        $filePart->id_file                         = '10';
        $filePart->id_file_part                    = '20';
        $filePart->filename                        = 'test.xliff';
        $filePart->id_file_part_external_reference = null;
        $filePart->tag_key                         = null;
        $filePart->tag_value                       = null;
        $filePart->reviewed_words_count            = '50';

        $this->sut->setFileParts([$filePart]);

        $issue = new ShapelessConcreteStruct();
        $issue->segment_id      = '101';
        $issue->content_id      = 'ct_1';
        $issue->penalty_points  = '3.5';
        $issue->cat_options     = json_encode((object)['code' => 'ACC']);
        $issue->cat_label       = 'Accuracy';
        $issue->severity_label  = 'minor';

        $entryDao = $this->createStub(EntryDao::class);
        $entryDao->method('getIssuesGroupedByIdFilePart')->willReturn([$issue]);
        $this->sut->setEntryDao($entryDao);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result  = $this->sut->render([$review]);
        $details = $result['quality_summary'][0]['details'];

        self::assertSame(3.5, $details[0]['issues_weight']);
        self::assertSame(1, $details[0]['issues_entries']);
        self::assertCount(1, $details[0]['issues']);

        $renderedIssue = $details[0]['issues'][0];
        self::assertSame(101, $renderedIssue['segment_id']);
        self::assertSame('ct_1', $renderedIssue['content_id']);
        self::assertSame(3.5, $renderedIssue['penalty_points']);
        self::assertSame('ACC', $renderedIssue['category_code']);
        self::assertSame('Accuracy', $renderedIssue['category_label']);
        self::assertSame('min', $renderedIssue['severity_code']);
        self::assertSame('minor', $renderedIssue['severity_label']);
    }

    #[Test]
    public function details_null_file_part_rendered_as_null(): void
    {
        $filePart = new ShapelessConcreteStruct();
        $filePart->id_file                         = '10';
        $filePart->id_file_part                    = null;
        $filePart->filename                        = 'test.xliff';
        $filePart->id_file_part_external_reference = null;
        $filePart->tag_key                         = null;
        $filePart->tag_value                       = null;
        $filePart->reviewed_words_count            = '50';

        $this->sut->setFileParts([$filePart]);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result  = $this->sut->render([$review]);
        $details = $result['quality_summary'][0]['details'];

        self::assertNull($details[0]['id_file_part']);
    }

    // ---------------------------------------------------------------
    // null guards (behavioral changes)
    // ---------------------------------------------------------------

    #[Test]
    public function throws_when_job_id_is_null(): void
    {
        $this->chunk->id = null;

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job id is required');

        $this->sut->render([$review]);
    }

    #[Test]
    public function throws_when_job_password_is_null(): void
    {
        $this->chunk->password = null;

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job password is required');

        $this->sut->render([$review]);
    }

    // ---------------------------------------------------------------
    // score formatting
    // ---------------------------------------------------------------

    #[Test]
    public function score_formatted_to_two_decimals(): void
    {
        $chunkReviewModel = $this->createStub(IChunkReviewModel::class);
        $chunkReviewModel->method('getScore')->willReturn(95.12345);
        $chunkReviewModel->method('getPenaltyPoints')->willReturn(2.5);
        $chunkReviewModel->method('getReviewedWordsCount')->willReturn(500);
        $chunkReviewModel->method('getQALimit')->willReturn(100);

        $revisionFactory = $this->createStub(RevisionFactory::class);
        $revisionFactory->method('getChunkReviewModel')->willReturn($chunkReviewModel);
        $this->sut->setRevisionFeature($revisionFactory);

        $review = new ChunkReviewStruct();
        $review->source_page = 2;
        $review->is_pass     = true;
        $review->total_tte   = 0;

        $result = $this->sut->render([$review]);
        $item   = $result['quality_summary'][0];

        self::assertEqualsWithDelta(95.12, $item['score'], 0.001);
        self::assertSame(2.5, $item['total_issues_weight']);
        self::assertSame(500, $item['total_reviewed_words_count']);
    }
}
