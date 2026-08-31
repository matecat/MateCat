<?php

declare(strict_types=1);

namespace Matecat\Core\Plugins\Features;

use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\Hook\Event\Filter\FilterCreateProjectFeaturesEvent;
use Model\FeaturesBase\Hook\Event\Run\AlterChunkReviewStructEvent;
use Model\FeaturesBase\Hook\Event\Run\ProjectCompletionEventSavedEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\ProjectCreation\ProjectStructure;
use Model\QualityReport\QualityReportModel;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\AbstractRevisionFeature;
use Plugins\Features\ReviewExtended;

/**
 * Records what resetScore() wrote instead of talking to a database, so the reset itself can be
 * asserted. getChunkReview() is seeded because the real one reads findChunkReviews(...)[0].
 */
class RecordingQualityReportModel extends QualityReportModel
{
    public ?ChunkReviewStruct $seededReview = null;
    public ?ChunkReviewStruct $lastUpdatedStruct = null;
    /** @var array<string, mixed> */
    public array $lastUpdatedOptions = [];

    public function getChunkReview(): ChunkReviewStruct
    {
        return $this->seededReview ?? throw new \LogicException('seed the review first');
    }

    protected function updateChunkReview(ChunkReviewStruct $chunkReview, array $options): void
    {
        $this->lastUpdatedStruct = $chunkReview;
        $this->lastUpdatedOptions = $options;
    }
}

class ConcreteTestRevisionFeature extends AbstractRevisionFeature
{
    public const string FEATURE_CODE = 'test_revision_feature';

    public ?RecordingQualityReportModel $qualityReportModel = null;

    public function callValidateUndoData(ChunkCompletionEventStruct $event, array $undoData): void
    {
        $this->_validateUndoData($event, $undoData);
    }

    public function callCreateChunkReviewRecords(ProjectStructure $projectStructure): void
    {
        $this->createChunkReviewRecords($projectStructure);
    }

    protected function createQualityReportModel(JobStruct $chunk): QualityReportModel
    {
        return $this->qualityReportModel ?? parent::createQualityReportModel($chunk);
    }
}

class ReviewExtendedProbe extends ReviewExtended
{
    public const string FEATURE_CODE = 'review_extended_probe';
}

class TestChunkReviewStruct extends ChunkReviewStruct
{
    public function getChunk(JobDao $jobDao): JobStruct
    {
        return new JobStruct([
            'id' => 1,
            'password' => 'pw',
        ]);
    }
}

class AbstractRevisionFeatureTest extends AbstractTest
{
    private ConcreteTestRevisionFeature $feature;
    private IDatabase $dbStub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feature = new ConcreteTestRevisionFeature(new BasicFeatureStruct([
            'feature_code' => ConcreteTestRevisionFeature::FEATURE_CODE,
        ]));
        // The split/merge and undo paths take qa_chunk_reviews row locks via
        // ChunkReviewDao::lockByJobId(), which refuses to run outside a transaction. In production
        // JobSplitMergeService and CompletionEventController open one before dispatching.
        [$this->dbStub, , $stmtStub] = $this->createDatabaseMock(inTransaction: true);
        $stmtStub->method('fetchAll')->willReturn([]);

        $this->feature->setDatabase($this->dbStub);
    }

    #[Test]
    public function filterCreateProjectFeaturesAddsFeatureToEvent(): void
    {
        $event = new FilterCreateProjectFeaturesEvent([]);

        $this->feature->filterCreateProjectFeatures($event);

        $projectFeatures = $event->getProjectFeatures();
        $this->assertArrayHasKey(ConcreteTestRevisionFeature::FEATURE_CODE, $projectFeatures);
        $this->assertInstanceOf(BasicFeatureStruct::class, $projectFeatures[ConcreteTestRevisionFeature::FEATURE_CODE]);
    }

    #[Test]
    public function validateUndoDataAcceptsValidPayload(): void
    {
        $event = new ChunkCompletionEventStruct();
        $event->id = 42;

        $undoData = [
            'reset_by_event_id' => '42',
            'penalty_points' => 10,
            'reviewed_words_count' => 100,
            'is_pass' => true,
        ];

        $this->feature->callValidateUndoData($event, $undoData);

        $this->assertTrue(true);
    }

    #[Test]
    public function validateUndoDataThrowsForMissingKeys(): void
    {
        $event = new ChunkCompletionEventStruct();
        $event->id = 42;

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Undo data is missing some keys');
        $this->feature->callValidateUndoData($event, ['reset_by_event_id' => '42']);
    }

    #[Test]
    public function validateUndoDataThrowsForMismatchedEventId(): void
    {
        $event = new ChunkCompletionEventStruct();
        $event->id = 42;

        $undoData = [
            'reset_by_event_id' => '999',
            'penalty_points' => 10,
            'reviewed_words_count' => 100,
            'is_pass' => true,
        ];

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Event does not match');
        $this->feature->callValidateUndoData($event, $undoData);
    }

    #[Test]
    public function getChunkReviewModelReturnsChunkReviewModelInstance(): void
    {
        $struct = new TestChunkReviewStruct([
            'id_project' => 1,
            'id_job' => 1,
            'password' => 'pw',
            'source_page' => 2,
        ]);

        $result = $this->feature->getChunkReviewModel($struct);
        $this->assertInstanceOf(\Plugins\Features\ReviewExtended\ChunkReviewModel::class, $result);
    }

    #[Test]
    public function loadAndValidateQualityFrameworkUsesTemplateWhenPresent(): void
    {
        $projectStructure = new ProjectStructure();
        $projectStructure->qa_model_template = ['model' => ['uid' => 1, 'version' => 1]];

        ConcreteTestRevisionFeature::loadAndValidateQualityFramework($projectStructure);

        $this->assertSame(
            ['model' => ['uid' => 1, 'version' => 1]],
            $projectStructure->features['quality_framework']
        );
        $this->assertSame([], $projectStructure->result['errors']);
    }

    #[Test]
    public function loadAndValidateQualityFrameworkLoadsModelFromFilePath(): void
    {
        $projectStructure = new ProjectStructure();
        $projectStructure->uid = 777;

        $tmpFile = tempnam(sys_get_temp_dir(), 'qa_model_');
        if ($tmpFile === false) {
            $this->fail('Unable to create temporary file');
        }

        file_put_contents($tmpFile, json_encode([
            'model' => [
                'uid' => 1,
                'version' => 1,
                'passfail' => ['type' => 'points', 'options' => []],
                'categories' => [],
                'severities' => [],
            ],
        ]));

        try {
            ConcreteTestRevisionFeature::loadAndValidateQualityFramework($projectStructure, $tmpFile);
            $this->assertSame(777, $projectStructure->features['quality_framework']['model']['uid']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function loadAndValidateQualityFrameworkAddsErrorWhenModelCannotBeLoaded(): void
    {
        $projectStructure = new ProjectStructure();

        ConcreteTestRevisionFeature::loadAndValidateQualityFramework($projectStructure, '/not/existing/qa_model.json');

        $this->assertNotEmpty($projectStructure->result['errors']);
        $this->assertSame('-900', $projectStructure->result['errors'][0]['code']);
        $this->assertNull($projectStructure->features['quality_framework']);
    }

    #[Test]
    public function loadAndValidateQualityFrameworkReturnsEarlyForReviewExtendedClass(): void
    {
        $projectStructure = new ProjectStructure();

        ReviewExtendedProbe::loadAndValidateQualityFramework($projectStructure, '/not/existing/qa_model.json');

        $this->assertArrayNotHasKey('quality_framework', $projectStructure->features);
        $this->assertSame([], $projectStructure->result['errors']);
    }

    #[Test]
    public function loadRoutesCanBeInvokedStatically(): void
    {
        ConcreteTestRevisionFeature::loadRoutes(new \Klein\Klein());
        $this->assertTrue(true);
    }

    #[Test]
    public function createChunkReviewRecordsThrowsWhenProjectIdMissing(): void
    {
        $projectStructure = new ProjectStructure();
        $projectStructure->id_project = null;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Project id is required to create chunk review records');
        $this->feature->callCreateChunkReviewRecords($projectStructure);
    }

    private function makeCompletionEventSavedEvent(): ProjectCompletionEventSavedEvent
    {
        return new ProjectCompletionEventSavedEvent(
            new JobStruct([
                'id' => 999999,
                'password' => 'pw',
                'id_project' => 1,
            ]),
            new CompletionEventStruct([
                'uid' => 1,
                'source' => 'test',
                'is_review' => true,
            ]),
            123
        );
    }

    #[Test]
    public function projectCompletionEventSavedResetsTheScoreAndSnapshotsUndoData(): void
    {
        $review = new ChunkReviewStruct([
            'id' => 444,
            'id_job' => 999999,
            'password' => 'pw',
            'source_page' => 2,
            'penalty_points' => 5.5,
            'reviewed_words_count' => 80,
            'is_pass' => false,
        ]);

        $model = new RecordingQualityReportModel($this->makeCompletionEventSavedEvent()->chunk, $this->dbStub);
        $model->seededReview = $review;
        $this->feature->qualityReportModel = $model;

        $this->feature->projectCompletionEventSaved($this->makeCompletionEventSavedEvent());

        $this->assertSame(0.0, $review->penalty_points);
        $this->assertSame(0, $review->reviewed_words_count);
        $this->assertSame($review, $model->lastUpdatedStruct);
        $this->assertSame(
            ['fields' => ['undo_data', 'penalty_points', 'reviewed_words_count', 'is_pass']],
            $model->lastUpdatedOptions
        );

        // undo_data has to carry the pre-reset values, or the reset is unrecoverable.
        $undoData = json_decode((string)$review->undo_data, true);
        $this->assertSame(123, $undoData['reset_by_event_id']);
        $this->assertSame(5.5, $undoData['penalty_points']);
        $this->assertSame(80, $undoData['reviewed_words_count']);
        $this->assertFalse($undoData['is_pass']);
    }

    /**
     * The regression guard for the chunk-completion 500. resetScore() takes the job's
     * qa_chunk_reviews row locks, and lockByJobId() refuses to run outside a transaction — so this
     * whole path threw until EventModel::save() opened one. Nothing could catch it while the shared
     * stub reported an open transaction unconditionally.
     */
    #[Test]
    public function projectCompletionEventSavedThrowsWhenNoTransactionIsOpen(): void
    {
        [$dbStub] = $this->createDatabaseMock(inTransaction: false);
        $this->feature->setDatabase($dbStub);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires an open transaction');
        $this->feature->projectCompletionEventSaved($this->makeCompletionEventSavedEvent());
    }

    private function makeAlterEvent(int $eventId = 77): AlterChunkReviewStructEvent
    {
        return new AlterChunkReviewStructEvent(new ChunkCompletionEventStruct([
            'id'                 => $eventId,
            'id_project'         => 1,
            'id_job'             => 999999,
            'password'           => 'pw',
            'source'             => 'test',
            'job_first_segment'  => 1,
            'job_last_segment'   => 10,
        ]));
    }

    /**
     * @param list<ChunkReviewStruct> $reviews
     */
    private function feedChunkReviews(array $reviews, bool $inTransaction = true): void
    {
        [$database, , $statement] = $this->createDatabaseMock(inTransaction: $inTransaction);
        $statement->method('fetchAll')->willReturn($reviews);

        $this->feature->setDatabase($database);
    }

    private function reviewWithUndoData(?string $undoData): ChunkReviewStruct
    {
        return new ChunkReviewStruct([
            'id'                   => 444,
            'id_job'               => 999999,
            'id_project'           => 1,
            'password'             => 'pw',
            'source_page'          => 2,
            'penalty_points'       => 0.0,
            'reviewed_words_count' => 0,
            'is_pass'              => true,
            'undo_data'            => $undoData,
        ]);
    }

    #[Test]
    public function alterChunkReviewStructRestoresTheSnapshottedValues(): void
    {
        $review = $this->reviewWithUndoData(json_encode([
            'reset_by_event_id'    => 77,
            'penalty_points'       => 5.5,
            'reviewed_words_count' => 80,
            'is_pass'              => false,
        ]));

        $this->feedChunkReviews([$review]);

        $this->feature->alterChunkReviewStruct($this->makeAlterEvent());

        // The undo restores the absolute values the reset had snapshotted, and clears the snapshot
        // so the same event cannot be undone twice.
        $this->assertSame(5.5, $review->penalty_points);
        $this->assertSame(80, $review->reviewed_words_count);
        $this->assertFalse($review->is_pass);
        $this->assertNull($review->undo_data);
    }

    #[Test]
    public function alterChunkReviewStructThrowsWhenTheChunkReviewIsMissing(): void
    {
        $this->feedChunkReviews([]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Chunk review not found');

        $this->feature->alterChunkReviewStruct($this->makeAlterEvent());
    }

    #[Test]
    public function alterChunkReviewStructThrowsWhenNothingWasSnapshotted(): void
    {
        $this->feedChunkReviews([$this->reviewWithUndoData(null)]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Undo data is not available');

        $this->feature->alterChunkReviewStruct($this->makeAlterEvent());
    }

    #[Test]
    public function alterChunkReviewStructThrowsWhenTheSnapshotBelongsToAnotherEvent(): void
    {
        $this->feedChunkReviews([$this->reviewWithUndoData(json_encode([
            'reset_by_event_id'    => 78,
            'penalty_points'       => 5.5,
            'reviewed_words_count' => 80,
            'is_pass'              => false,
        ]))]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Event does not match with latest revision data');

        $this->feature->alterChunkReviewStruct($this->makeAlterEvent());
    }

    #[Test]
    public function alterChunkReviewStructRefusesToRunOutsideATransaction(): void
    {
        $this->feedChunkReviews([], inTransaction: false);

        $this->expectException(\RuntimeException::class);

        $this->feature->alterChunkReviewStruct($this->makeAlterEvent());
    }

}
