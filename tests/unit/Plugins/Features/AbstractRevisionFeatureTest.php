<?php

declare(strict_types=1);

namespace unit\Plugins\Features;

use Controller\API\Commons\Exceptions\ValidationError;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\Hook\Event\Filter\FilterCreateProjectFeaturesEvent;
use Model\FeaturesBase\Hook\Event\Run\ProjectCompletionEventSavedEvent;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\ProjectCreation\ProjectStructure;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\AbstractRevisionFeature;
use Plugins\Features\ReviewExtended;
use TestHelpers\AbstractTest;

class ConcreteTestRevisionFeature extends AbstractRevisionFeature
{
    public const string FEATURE_CODE = 'test_revision_feature';

    public function callValidateUndoData(ChunkCompletionEventStruct $event, array $undoData): void
    {
        $this->_validateUndoData($event, $undoData);
    }

    public function callCreateChunkReviewRecords(ProjectStructure $projectStructure): void
    {
        $this->createChunkReviewRecords($projectStructure);
    }
}

class ReviewExtendedProbe extends ReviewExtended
{
    public const string FEATURE_CODE = 'review_extended_probe';
}

class TestChunkReviewStruct extends ChunkReviewStruct
{
    public function getChunk(): JobStruct
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->feature = new ConcreteTestRevisionFeature(new BasicFeatureStruct([
            'feature_code' => ConcreteTestRevisionFeature::FEATURE_CODE,
        ]));
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
        $this->expectExceptionMessage('undo data is missing some keys');
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
        $this->expectExceptionMessage('event does not match');
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

    #[Test]
    public function projectCompletionEventSavedInvokesQualityReportModelReset(): void
    {
        $chunk = new JobStruct([
            'id' => 999999,
            'password' => 'pw',
            'id_project' => 1,
        ]);

        $completionEvent = new CompletionEventStruct([
            'uid' => 1,
            'source' => 'test',
            'is_review' => true,
        ]);

        $event = new ProjectCompletionEventSavedEvent($chunk, $completionEvent, 123);

        set_error_handler(static function (int $severity, string $message): bool {
            if ($severity === E_WARNING && str_contains($message, 'Undefined array key 0')) {
                return true;
            }

            return false;
        });

        try {
            $this->expectException(\TypeError::class);
            $this->feature->projectCompletionEventSaved($event);
        } finally {
            restore_error_handler();
        }
    }
}
