<?php

declare(strict_types=1);

namespace unit\Model\QualityReport;

use DateMalformedStringException;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\QualityReport\QualityReportDao;
use Model\QualityReport\QualityReportModel;
use Model\ReviseFeedback\FeedbackDAO;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ReviewExtended\IChunkReviewModel;
use Plugins\Features\RevisionFactory;
use ReflectionException;
use ReflectionMethod;
use TestHelpers\AbstractTest;

class QualityReportModelTest extends AbstractTest
{
    private QualityReportDao $qualityReportDao;
    private ChunkReviewDao $chunkReviewDao;
    private FeedbackDAO $feedbackDao;
    private RevisionFactory $revisionFactory;
    private IChunkReviewModel $defaultChunkReviewModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultChunkReviewModel = $this->createConfiguredStub(IChunkReviewModel::class, [
            'getScore' => 0.0,
        ]);

        $this->qualityReportDao = $this->createConfiguredStub(QualityReportDao::class, [
            'getAverages' => [
                'avg_edit_distance' => 0,
                'avg_time_to_edit' => 0,
            ],
        ]);
        $this->chunkReviewDao = $this->createConfiguredStub(ChunkReviewDao::class, [
            'findChunkReviews' => [],
        ]);
        $this->feedbackDao = $this->createConfiguredStub(FeedbackDAO::class, [
            'getFeedback' => null,
        ]);
        $this->revisionFactory = $this->createConfiguredStub(RevisionFactory::class, [
            'getChunkReviewModel' => $this->defaultChunkReviewModel,
        ]);
    }

    private function createProjectStub(): ProjectStruct
    {
        $project = $this->createConfiguredStub(ProjectStruct::class, [
            'getAllMetadataAsKeyValue' => ['domain' => 'medical'],
        ]);
        $project->id = 123;
        $project->create_date = '2024-01-02 03:04:05';

        return $project;
    }

    private function createChunkStub(ProjectStruct $project): JobStruct
    {
        $chunk = $this->createConfiguredStub(JobStruct::class, [
            'getProject' => $project,
        ]);
        $chunk->id = 10;
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        return $chunk;
    }

    private function createModel(): TestableQualityReportModel
    {
        return $this->createModelWithDoubles();
    }

    /** @param array<int, array<string, mixed>> $segments */
    private function createModelWithDoubles(
        ?QualityReportDao $qualityReportDao = null,
        ?ChunkReviewDao $chunkReviewDao = null,
        ?FeedbackDAO $feedbackDao = null,
        ?RevisionFactory $revisionFactory = null,
        array $segments = [],
    ): TestableQualityReportModel {
        $project = $this->createProjectStub();
        $chunk = $this->createChunkStub($project);

        $model = new TestableQualityReportModel(
            $chunk,
            $qualityReportDao ?? $this->qualityReportDao,
            $chunkReviewDao ?? $this->chunkReviewDao,
            $feedbackDao ?? $this->feedbackDao,
        );
        $model->setRevisionFactoryForTest($revisionFactory ?? $this->revisionFactory);
        $model->setSegmentsForQualityReportForTest($segments);

        return $model;
    }

    private function createFeedbackStruct(string $feedback): ShapelessConcreteStruct
    {
        $feedbackStruct = new ShapelessConcreteStruct();
        $feedbackStruct->feedback = $feedback;

        return $feedbackStruct;
    }

    private function makeChunkReview(
        ?int $id = 99,
        ?float $penaltyPoints = 7.5,
        int $reviewedWordsCount = 120,
        ?bool $isPass = false,
        string $reviewPassword = 'review-pwd',
        int $sourcePage = 2,
    ): ChunkReviewStruct {
        $review = new ChunkReviewStruct();
        $review->id = $id;
        $review->id_job = 10;
        $review->id_project = 123;
        $review->password = 'chunk-pwd';
        $review->review_password = $reviewPassword;
        $review->source_page = $sourcePage;
        $review->penalty_points = $penaltyPoints;
        $review->reviewed_words_count = $reviewedWordsCount;
        $review->is_pass = $isPass;

        return $review;
    }

    /** @return array<string, mixed> */
    private function baseRecord(array $overrides = []): array
    {
        return array_merge([
            'file_id' => 1,
            'file_filename' => 'file-a.xlf',
            'segment_id' => 100,
            'segment_source' => 'Hello',
            'translation' => 'Ciao',
            'translation_status' => 'APPROVED',
            'edit_distance' => 1500,
            'time_to_edit' => 2500,
            'original_translation' => null,
            'issue_id' => 500,
            'issue_create_date' => '2024-06-10 10:11:12',
            'issue_category' => 'TYPO',
            'category_options' => ['spellcheck' => true],
            'issue_severity' => 'minor',
            'issue_start_offset' => 1,
            'issue_end_offset' => 4,
            'target_text' => 'Ciao',
            'issue_comment' => 'Fix typo',
            'issue_replies_count' => 2,
            'comment_id' => 700,
            'comment_comment' => 'Done',
            'comment_create_date' => '2024-06-11 09:08:07',
            'comment_uid' => 33,
        ], $overrides);
    }

    #[Test]
    public function ConstructGetChunkAndGetProject(): void
    {
        $project = $this->createProjectStub();
        $chunk = $this->createChunkStub($project);
        $model = new QualityReportModel($chunk);

        $this->assertSame($chunk, $model->getChunk());
        $this->assertSame($project, $model->getProject());
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function FilterDateReturnsNullWhenInputIsNull(): void
    {
        $model = $this->createModel();

        $this->assertNull($model->invokeFilterDate(null));
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function FilterDateReturnsOriginalValueWithoutFormat(): void
    {
        $model = $this->createModel();

        $this->assertSame('2024-07-08 11:22:33', $model->invokeFilterDate('2024-07-08 11:22:33'));
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function SetDateFormatAffectsFilterDate(): void
    {
        $model = $this->createModel();
        $model->setDateFormat('Y/m/d');

        $this->assertSame('2024/07/08', $model->invokeFilterDate('2024-07-08 11:22:33'));
    }

    #[Test]
    public function GetAndDecodePossiblyProjectMetadataJsonReturnsProjectMetadata(): void
    {
        $model = $this->createModel();

        $this->assertSame(['domain' => 'medical'], $model->invokeGetAndDecodePossiblyProjectMetadataJson());
    }

    #[Test]
    public function StructureNestFileCreatesFileEntryInStructure(): void
    {
        $model = $this->createModel();
        $model->setQualityReportStructureForTest(['chunk' => ['files' => []]]);

        $model->invokeStructureNestFile($this->baseRecord([
            'file_id' => 11,
            'file_filename' => 'nested-file.xlf',
        ]));

        $structure = $model->getQualityReportStructureForTest();
        $this->assertCount(1, $structure['chunk']['files']);
        $this->assertSame(11, $structure['chunk']['files'][0]['id']);
        $this->assertSame('nested-file.xlf', $structure['chunk']['files'][0]['filename']);
        $this->assertSame([], $structure['chunk']['files'][0]['segments']);
    }

    #[Test]
    public function StructureNestSegmentUsesTranslationAsOriginalWhenOriginalIsNull(): void
    {
        $model = $this->createModel();
        $model->setQualityReportStructureForTest(['chunk' => ['files' => []]]);
        $record = $this->baseRecord([
            'translation' => 'Translated value',
            'original_translation' => null,
            'edit_distance' => 1234,
            'time_to_edit' => 5678,
        ]);

        $model->invokeStructureNestFile($record);
        $model->invokeStructureNestSegment($record);

        $structure = $model->getQualityReportStructureForTest();
        $segment = $structure['chunk']['files'][0]['segments'][0];

        $this->assertSame('Translated value', $segment['original_translation']);
        $this->assertSame('Translated value', $segment['translation']);
        $this->assertSame(1.23, $segment['edit_distance']);
        $this->assertSame(5.68, $segment['time_to_edit']);
        $this->assertSame([], $segment['issues']);
        $this->assertSame([], $segment['qa_checks']);
    }

    #[Test]
    public function StructureNestIssueAndCommentPopulateNestedArrays(): void
    {
        $model = $this->createModel();
        $model->setDateFormat('Y-m-d');
        $model->setQualityReportStructureForTest(['chunk' => ['files' => []]]);
        $record = $this->baseRecord();

        $model->invokeStructureNestFile($record);
        $model->invokeStructureNestSegment($record);
        $model->invokeStructureNestIssue($record);
        $model->invokeStructureNestComment($record);

        $structure = $model->getQualityReportStructureForTest();
        $issue = $structure['chunk']['files'][0]['segments'][0]['issues'][0];

        $this->assertSame(500, $issue['id']);
        $this->assertSame('2024-06-10', $issue['created_at']);
        $this->assertSame('TYPO', $issue['category']);
        $this->assertSame('minor', $issue['severity']);
        $this->assertSame(1, $issue['start_offset']);
        $this->assertSame(4, $issue['end_offset']);
        $this->assertCount(1, $issue['comments']);
        $this->assertSame('Done', $issue['comments'][0]['comment']);
        $this->assertSame('2024-06-11', $issue['comments'][0]['created_at']);
        $this->assertSame(33, $issue['comments'][0]['uid']);
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function BuildFilesSegmentsNestedTreeBuildsFileSegmentIssueCommentHierarchy(): void
    {
        $model = $this->createModel();
        $model->setDateFormat('Y-m-d');
        $model->setQualityReportStructureForTest(['chunk' => ['files' => []]]);

        $records = [
            $this->baseRecord([
                'file_id' => 1,
                'file_filename' => 'file-1.xlf',
                'segment_id' => 10,
                'issue_id' => 1000,
                'comment_id' => 2000,
                'comment_comment' => 'c1',
            ]),
            $this->baseRecord([
                'file_id' => 1,
                'file_filename' => 'file-1.xlf',
                'segment_id' => 10,
                'issue_id' => 1000,
                'comment_id' => 2001,
                'comment_comment' => 'c2',
            ]),
            $this->baseRecord([
                'file_id' => 1,
                'file_filename' => 'file-1.xlf',
                'segment_id' => 11,
                'issue_id' => null,
                'comment_id' => null,
                'translation' => 'Alt',
                'original_translation' => 'Orig',
            ]),
            $this->baseRecord([
                'file_id' => 2,
                'file_filename' => 'file-2.xlf',
                'segment_id' => 20,
                'issue_id' => 1002,
                'comment_id' => null,
            ]),
        ];

        $model->invokeBuildFilesSegmentsNestedTree($records);
        $structure = $model->getQualityReportStructureForTest();

        $this->assertCount(2, $structure['chunk']['files']);

        $file1 = $structure['chunk']['files'][0];
        $this->assertSame('file-1.xlf', $file1['filename']);
        $this->assertCount(2, $file1['segments']);
        $this->assertCount(1, $file1['segments'][0]['issues']);
        $this->assertCount(2, $file1['segments'][0]['issues'][0]['comments']);
        $this->assertSame('c1', $file1['segments'][0]['issues'][0]['comments'][0]['comment']);
        $this->assertSame('c2', $file1['segments'][0]['issues'][0]['comments'][1]['comment']);
        $this->assertSame('Orig', $file1['segments'][1]['original_translation']);
        $this->assertCount(0, $file1['segments'][1]['issues']);

        $file2 = $structure['chunk']['files'][1];
        $this->assertSame('file-2.xlf', $file2['filename']);
        $this->assertCount(1, $file2['segments']);
        $this->assertCount(1, $file2['segments'][0]['issues']);
        $this->assertCount(0, $file2['segments'][0]['issues'][0]['comments']);
    }

    #[Test]
    public function GetChunkReviewReturnsCachedReviewWhenAlreadySet(): void
    {
        $model = $this->createModel();
        $expected = $this->makeChunkReview(id: 111);
        $model->setChunkReviewForTest($expected);

        $actual = $model->getChunkReview();

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function GetChunkReviewLoadsFromDaoWhenNotCached(): void
    {
        $expected = $this->makeChunkReview(id: 222);
        $chunkReviewDao = $this->createConfiguredStub(ChunkReviewDao::class, [
            'findChunkReviews' => [$expected],
        ]);
        $model = $this->createModelWithDoubles(chunkReviewDao: $chunkReviewDao);

        $actual = $model->getChunkReview();

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function GetChunkReviewModelReturnsCachedModelWhenAlreadySet(): void
    {
        $model = $this->createModel();
        $cachedModel = $this->createConfiguredStub(IChunkReviewModel::class, [
            'getScore' => 55.5,
        ]);
        $model->setChunkReviewModelForTest($cachedModel);

        $this->assertSame($cachedModel, $model->getChunkReviewModel());
    }

    #[Test]
    public function GetChunkReviewModelLoadsFromRevisionFactoryWhenNotCached(): void
    {
        $expectedModel = $this->createConfiguredStub(IChunkReviewModel::class, [
            'getScore' => 23.45,
        ]);

        $revisionFactory = $this->createConfiguredStub(RevisionFactory::class, [
            'getChunkReviewModel' => $expectedModel,
        ]);
        $model = $this->createModelWithDoubles(revisionFactory: $revisionFactory);
        $model->setChunkReviewForTest($this->makeChunkReview(id: 333));

        $loaded = $model->getChunkReviewModel();

        $this->assertSame($expectedModel, $loaded);
        $this->assertSame(23.45, $loaded->getScore());
    }

    #[Test]
    public function GetScoreFormatsChunkReviewModelScoreWithTwoDecimals(): void
    {
        $model = $this->createModel();
        $chunkReviewModel = $this->createConfiguredStub(IChunkReviewModel::class, [
            'getScore' => 12.3456,
        ]);
        $model->setChunkReviewModelForTest($chunkReviewModel);

        $this->assertSame('12.35', $model->getScore());
    }

    #[Test]
    public function ResetScoreResetsFieldsAndStoresUndoDataWithoutDatabaseCall(): void
    {
        $model = $this->createModel();
        $review = $this->makeChunkReview(id: 444, penaltyPoints: 5.5, reviewedWordsCount: 80, isPass: false);
        $model->setChunkReviewForTest($review);

        $model->resetScore(77);

        $this->assertSame(0.0, $review->penalty_points);
        $this->assertSame(0, $review->reviewed_words_count);
        $this->assertTrue($review->is_pass);
        $this->assertNotNull($review->undo_data);
        $this->assertSame($review, $model->lastUpdatedStruct);
        $this->assertSame(
            ['fields' => ['undo_data', 'penalty_points', 'reviewed_words_count', 'is_pass']],
            $model->lastUpdatedOptions
        );
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function BuildQualityReportStructureBuildsTopLevelAndNestedData(): void
    {
        $qualityReportDao = $this->createConfiguredStub(QualityReportDao::class, [
            'getAverages' => [
                'avg_edit_distance' => 2500,
                'avg_time_to_edit' => 7654,
            ],
        ]);
        $chunkReviewDao = $this->createConfiguredStub(ChunkReviewDao::class, [
            'findChunkReviews' => [
                $this->makeChunkReview(id: 1, sourcePage: 3, reviewPassword: 'r1', isPass: true),
            ],
        ]);
        $feedbackDao = $this->createConfiguredStub(FeedbackDAO::class, [
            'getFeedback' => $this->createFeedbackStruct('5'),
        ]);
        $chunkReviewModel = $this->createConfiguredStub(IChunkReviewModel::class, [
            'getScore' => 66.7,
        ]);
        $revisionFactory = $this->createConfiguredStub(RevisionFactory::class, [
            'getChunkReviewModel' => $chunkReviewModel,
        ]);

        $model = $this->createModelWithDoubles(
            qualityReportDao: $qualityReportDao,
            chunkReviewDao: $chunkReviewDao,
            feedbackDao: $feedbackDao,
            revisionFactory: $revisionFactory,
        );
        $model->setDateFormat('Y-m-d');

        $records = [
            $this->baseRecord([
                'file_id' => 9,
                'file_filename' => 'report-file.xlf',
                'segment_id' => 901,
                'issue_id' => 9901,
                'comment_id' => 9991,
            ]),
        ];

        $structure = $model->invokeBuildQualityReportStructure($records);

        $this->assertSame(2.5, $structure['chunk']['avg_edit_distance']);
        $this->assertSame(7.65, $structure['chunk']['avg_time_to_edit']);
        $this->assertSame('en-US', $structure['job']['source']);
        $this->assertSame('it-IT', $structure['job']['target']);
        $this->assertSame(['domain' => 'medical'], $structure['project']['metadata']);
        $this->assertSame(123, $structure['project']['id']);
        $this->assertSame('2024-01-02', $structure['project']['created_at']);
        $this->assertCount(1, $structure['chunk']['files']);
        $this->assertCount(1, $structure['chunk']['reviews']);
        $this->assertSame(2, $structure['chunk']['reviews'][0]['revision_number']);
        $this->assertSame('5', $structure['chunk']['reviews'][0]['feedback']);
        $this->assertTrue($structure['chunk']['reviews'][0]['is_pass']);
        $this->assertSame(66.7, $structure['chunk']['reviews'][0]['score']);
        $this->assertSame('reviewer-from-test', $structure['chunk']['reviews'][0]['reviewer_name']);
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function BuildQualityReportStructureSetsAveragesToZeroWhenDaoReturnsFalse(): void
    {
        $qualityReportDao = $this->createConfiguredStub(QualityReportDao::class, [
            'getAverages' => false,
        ]);
        $model = $this->createModelWithDoubles(qualityReportDao: $qualityReportDao);

        $structure = $model->invokeBuildQualityReportStructure([]);

        $this->assertSame(0.0, $structure['chunk']['avg_edit_distance']);
        $this->assertSame(0.0, $structure['chunk']['avg_time_to_edit']);
    }

    /** @throws DateMalformedStringException */
    #[Test]
    public function GetStructureUsesDaoRecordsAndReturnsBuiltStructure(): void
    {
        $qualityReportDao = $this->createConfiguredStub(QualityReportDao::class, [
            'getAverages' => [
                'avg_edit_distance' => 1000,
                'avg_time_to_edit' => 2000,
            ],
        ]);

        $segments = [
            $this->baseRecord([
                'file_id' => 55,
                'file_filename' => 'via-get-structure.xlf',
                'segment_id' => 551,
                'issue_id' => null,
                'comment_id' => null,
            ]),
        ];

        $model = $this->createModelWithDoubles(qualityReportDao: $qualityReportDao, segments: $segments);
        $model->setDateFormat('Y-m-d');

        $structure = $model->getStructure();

        $this->assertSame('via-get-structure.xlf', $structure['chunk']['files'][0]['filename']);
        $this->assertSame(1.0, $structure['chunk']['avg_edit_distance']);
        $this->assertSame(2.0, $structure['chunk']['avg_time_to_edit']);
    }

    #[Test]
    public function AttachReviewsDataAppendsReviewEntries(): void
    {
        $chunkReviewDao = $this->createConfiguredStub(ChunkReviewDao::class, [
            'findChunkReviews' => [
                $this->makeChunkReview(id: 1, sourcePage: 3, reviewPassword: 'pw-1', isPass: true),
                $this->makeChunkReview(id: 2, sourcePage: 4, reviewPassword: 'pw-2', isPass: null),
            ],
        ]);
        $feedbackDao = $this->createConfiguredStub(FeedbackDAO::class, [
            'getFeedback' => $this->createFeedbackStruct('excellent'),
        ]);
        $chunkReviewModel = $this->createConfiguredStub(IChunkReviewModel::class, [
            'getScore' => 91.23,
        ]);
        $revisionFactory = $this->createConfiguredStub(RevisionFactory::class, [
            'getChunkReviewModel' => $chunkReviewModel,
        ]);

        $model = $this->createModelWithDoubles(
            chunkReviewDao: $chunkReviewDao,
            feedbackDao: $feedbackDao,
            revisionFactory: $revisionFactory,
        );
        $model->setQualityReportStructureForTest([
            'chunk' => ['files' => []],
            'job' => [],
            'project' => [],
        ]);

        $model->invokeAttachReviewsData();
        $structure = $model->getQualityReportStructureForTest();

        $this->assertCount(2, $structure['chunk']['reviews']);
        $this->assertSame(2, $structure['chunk']['reviews'][0]['revision_number']);
        $this->assertSame(3, $structure['chunk']['reviews'][1]['revision_number']);
        $this->assertSame('excellent', $structure['chunk']['reviews'][0]['feedback']);
        $this->assertNull($structure['chunk']['reviews'][1]['is_pass']);
        $this->assertSame(91.23, $structure['chunk']['reviews'][0]['score']);
        $this->assertSame('reviewer-from-test', $structure['chunk']['reviews'][0]['reviewer_name']);
    }
}

class TestableQualityReportModel extends QualityReportModel
{
    public ?ChunkReviewStruct $lastUpdatedStruct = null;

    /** @var array<string, mixed> */
    public array $lastUpdatedOptions = [];

    /** @var array<int, array<string, mixed>> */
    private array $segmentsForQualityReport = [];

    private ?RevisionFactory $revisionFactoryForTest = null;

    /** @param array<int, array<string, mixed>> $segments */
    public function setSegmentsForQualityReportForTest(array $segments): void
    {
        $this->segmentsForQualityReport = $segments;
    }

    public function setRevisionFactoryForTest(RevisionFactory $revisionFactory): void
    {
        $this->revisionFactoryForTest = $revisionFactory;
    }

    /** @return array<int, array<string, mixed>> */
    protected function getSegmentsForQualityReport(): array
    {
        return $this->segmentsForQualityReport;
    }

    protected function createRevisionFactory(): RevisionFactory
    {
        if ($this->revisionFactoryForTest === null) {
            throw new \RuntimeException('RevisionFactory test double not set');
        }

        return $this->revisionFactoryForTest;
    }

    /** @param array<string, mixed> $options */
    protected function updateChunkReview(ChunkReviewStruct $chunkReview, array $options): void
    {
        $this->lastUpdatedStruct = $chunkReview;
        $this->lastUpdatedOptions = $options;
    }

    protected function getReviewerName(): string
    {
        return 'reviewer-from-test';
    }

    public function setQualityReportStructureForTest(array $structure): void
    {
        $property = new \ReflectionProperty(QualityReportModel::class, 'quality_report_structure');
        $property->setValue($this, $structure);
    }

    /** @return array<string, mixed> */
    public function getQualityReportStructureForTest(): array
    {
        $property = new \ReflectionProperty(QualityReportModel::class, 'quality_report_structure');

        /** @var array<string, mixed> $value */
        $value = $property->getValue($this);

        return $value;
    }

    /** @throws DateMalformedStringException */
    public function invokeFilterDate(?string $date): ?string
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'filterDate');

        return $method->invoke($this, $date);
    }

    /** @param array<string, mixed> $record */
    public function invokeStructureNestFile(array $record): void
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'structureNestFile');
        $method->invoke($this, $record);
    }

    /** @param array<string, mixed> $record */
    public function invokeStructureNestSegment(array $record): void
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'structureNestSegment');
        $method->invoke($this, $record);
    }

    /**
     * @param array<string, mixed> $record
     * @throws DateMalformedStringException
     */
    public function invokeStructureNestIssue(array $record): void
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'structureNestIssue');
        $method->invoke($this, $record);
    }

    /**
     * @param array<string, mixed> $record
     * @throws DateMalformedStringException
     */
    public function invokeStructureNestComment(array $record): void
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'structureNestComment');
        $method->invoke($this, $record);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @throws DateMalformedStringException
     */
    public function invokeBuildFilesSegmentsNestedTree(array $records): void
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'buildFilesSegmentsNestedTree');
        $method->invoke($this, $records);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     * @throws DateMalformedStringException
     */
    public function invokeBuildQualityReportStructure(array $records): array
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'buildQualityReportStructure');

        /** @var array<string, mixed> $value */
        $value = $method->invoke($this, $records);

        return $value;
    }

    /** @return array<string, mixed>
     * @throws ReflectionException
     */
    public function invokeGetAndDecodePossiblyProjectMetadataJson(): array
    {
        $method = new ReflectionMethod(QualityReportModel::class, 'getAndDecodePossiblyProjectMetadataJson');

        /** @var array<string, mixed> $value */
        $value = $method->invoke($this);

        return $value;
    }

    public function invokeAttachReviewsData(): void
    {
        $method = new ReflectionMethod(QualityReportModel::class, '_attachReviewsData');
        $method->invoke($this);
    }

    public function setChunkReviewForTest(ChunkReviewStruct $review): void
    {
        $property = new \ReflectionProperty(QualityReportModel::class, 'chunk_review');
        $property->setValue($this, $review);
    }

    public function setChunkReviewModelForTest(IChunkReviewModel $model): void
    {
        $property = new \ReflectionProperty(QualityReportModel::class, 'chunk_review_model');
        $property->setValue($this, $model);
    }
}
