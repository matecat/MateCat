<?php

namespace Matecat\Core\Model\ProjectCreation;

use Error;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\PostProjectCreateEvent;
use Model\Files\MetadataDao;
use Model\ProjectCreation\FileInsertionService;
use Model\ProjectCreation\JobCreationService;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectCreationException;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\QAProcessor;
use Model\ProjectCreation\SegmentExtractor;
use Model\ProjectCreation\SegmentStorageService;
use Model\ProjectCreation\TmKeyService;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectStruct;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Unit tests for the failure path of {@see \Model\ProjectCreation\ProjectManager::createProject()}.
 *
 * Every failure ends in the single catch block, which:
 * - records exactly one error for the user, unless a validator already recorded why the project is invalid;
 *   only a ProjectCreationException shows its own code and message, anything else gets the generic error
 * - deletes the records written so far (clearFailedProject); a failure after the commit keeps the project
 * - acks the message with an EndQueueException carrying the recorded code and the original as previous
 *
 * Most failures are raised by collaborators reached early in the pipeline (the xliff_parameters
 * normalization, the ValidateProjectCreationEvent dispatch, TmKeyService::setPrivateTMKeys()), so the
 * upload directory is never resolved and no storage or queue is touched. The finalize, extraction and
 * fast analysis tests run the pipeline to its end with stubbed collaborators, a temporary upload
 * directory and the test database.
 */
class CreateProjectFailureTest extends AbstractTest
{
    private const string GENERIC_MESSAGE = 'Project creation failed. Please try again.';

    /** An id no fixture uses, so the finalize's updateAnalysisStatus() matches no row. */
    private const int MISSING_PROJECT_ID = 2_147_000_002;

    private TestableProjectManager $pm;
    private string $originalFileStorageMethod;
    private string $originalQueueRepository;
    private string $originalAnalysisRepository;
    private string $queueRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        // The finalize tests resolve, and then delete, a real upload directory, and store the fast
        // analysis file next to it
        $this->originalQueueRepository = AppConfig::$QUEUE_PROJECT_REPOSITORY;
        $this->originalAnalysisRepository = AppConfig::$ANALYSIS_FILES_REPOSITORY;
        $this->queueRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'create_project_failure_' . uniqid();
        mkdir($this->queueRoot . DIRECTORY_SEPARATOR . 'upload', 0755, true);
        AppConfig::$QUEUE_PROJECT_REPOSITORY = $this->queueRoot;
        AppConfig::$ANALYSIS_FILES_REPOSITORY = $this->queueRoot;

        $this->pm = $this->makeProjectManager($this->createStub(FeatureSet::class));
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        AppConfig::$QUEUE_PROJECT_REPOSITORY = $this->originalQueueRepository;
        AppConfig::$ANALYSIS_FILES_REPOSITORY = $this->originalAnalysisRepository;

        foreach (glob($this->queueRoot . DIRECTORY_SEPARATOR . '*') ?: [] as $entry) {
            is_dir($entry) ? rmdir($entry) : unlink($entry);
        }
        rmdir($this->queueRoot);

        parent::tearDown();
    }

    private function makeProjectManager(FeatureSet $features): TestableProjectManager
    {
        $pm = new TestableProjectManager();
        $pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );

        // Production reserves id_project before the enqueue, so a null id never reaches createProject():
        // these tests force it to cover the defensive guard in clearFailedProject().
        $pm->setProjectStructureValue('id_project', null);
        $pm->setProjectStructureValue('uid', null);
        $pm->setProjectStructureValue('qa_model', null);

        return $pm;
    }

    /**
     * Make setPrivateTMKeys() — the first step that runs only when private keys are requested — run $onSetKeys.
     *
     * @param callable(ProjectStructure, ?string): void $onSetKeys
     */
    private function failOnPrivateTmKeys(callable $onSetKeys): void
    {
        $this->pm->setProjectStructureValue('private_tm_key', [['key' => 'abc123', 'r' => true, 'w' => true]]);

        $service = $this->createStub(TmKeyService::class);
        $service->method('setPrivateTMKeys')->willReturnCallback($onSetKeys);
        $this->pm->setTmKeyService($service);
    }

    private function expectNoProjectDeletion(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $this->pm->setProjectManagerModel($model);
    }

    private function runCreateProject(): EndQueueException
    {
        try {
            $this->pm->createProject();
        } catch (EndQueueException $e) {
            return $e;
        }

        self::fail('Expected EndQueueException');
    }

    // ── ProjectCreationException: its code and message reach the user ──

    #[Test]
    public function aProjectCreationExceptionRecordsItsCodeAndMessage(): void
    {
        $this->expectNoProjectDeletion();

        $original = new ProjectCreationException(ProjectCreationError::TM_KEY_INVALID, 'The TM key is not valid.');
        $this->failOnPrivateTmKeys(static function () use ($original): void {
            throw $original;
        });

        $thrown = $this->runCreateProject();

        self::assertSame(
            [['code' => ProjectCreationError::TM_KEY_INVALID->value, 'message' => 'The TM key is not valid.']],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertSame(ProjectCreationError::TM_KEY_INVALID->value, $thrown->getCode());
        self::assertSame($original, $thrown->getPrevious());
    }

    // ── Internal failure: the user gets the generic error ──────────

    /**
     * @return array<string, array{Throwable}>
     */
    public static function internalFailures(): array
    {
        $pdoException = new PDOException("SQLSTATE[HY000]: General error: 2006 MySQL server has gone away");
        (new ReflectionProperty(Exception::class, 'code'))->setValue($pdoException, 'HY000');

        return [
            'a PDOException with an SQLSTATE code' => [$pdoException],
            // An HTTP client carries the status as its code: 400 is also INVALID_XLIFF_PARAMETERS
            'a foreign exception with a ProjectCreationError number' => [new RuntimeException('Bad request from a remote service', 400)],
        ];
    }

    #[Test]
    #[DataProvider('internalFailures')]
    public function anInternalFailureRecordsTheGenericError(Throwable $original): void
    {
        $this->expectNoProjectDeletion();

        $this->failOnPrivateTmKeys(static function () use ($original): void {
            throw $original;
        });

        $thrown = $this->runCreateProject();

        self::assertSame(
            [['code' => ProjectCreationError::CREATION_FAILED->value, 'message' => self::GENERIC_MESSAGE]],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertSame(ProjectCreationError::CREATION_FAILED->value, $thrown->getCode());
        self::assertSame($original, $thrown->getPrevious());
    }

    // ── Validation failure: the validator's error is the only one ──

    #[Test]
    public function aValidationFailureKeepsOnlyTheErrorTheValidatorRecorded(): void
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')->willReturnCallback(function (object $event): object {
            $this->pm->getTestProjectStructure()->addError(-999, 'Invalid team.');

            return $event;
        });
        $this->pm = $this->makeProjectManager($features);
        $this->expectNoProjectDeletion();

        $thrown = $this->runCreateProject();

        self::assertSame(
            [['code' => -999, 'message' => 'Invalid team.']],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertInstanceOf(EndQueueException::class, $thrown->getPrevious());
        self::assertSame('Invalid project found.', $thrown->getPrevious()->getMessage());
    }

    // ── Cleanup of the records written so far ──────────────────────

    #[Test]
    public function aFailureAfterTheProjectRecordExistsDeletesTheProject(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())->method('deleteProject')->with(77);
        $this->pm->setProjectManagerModel($model);

        // The project id is read when the failure is handled, not when createProject() starts
        $this->failOnPrivateTmKeys(static function (ProjectStructure $projectStructure): void {
            $projectStructure->id_project = 77;

            throw new ProjectCreationException(ProjectCreationError::TM_KEY_INVALID, 'The TM key is not valid.');
        });

        $this->runCreateProject();

        self::assertCount(1, $this->pm->getTestProjectStructure()->result['errors']);
    }

    #[Test]
    public function theErrorIsRecordedBeforeTheCleanupRuns(): void
    {
        $errorsAtDeletion = null;
        $model = $this->createStub(ProjectManagerModel::class);
        $model->method('deleteProject')->willReturnCallback(function () use (&$errorsAtDeletion): void {
            $errorsAtDeletion = $this->pm->getTestProjectStructure()->result['errors'];

            throw new PDOException('Lock wait timeout exceeded');
        });
        $this->pm->setProjectManagerModel($model);

        $original = new ProjectCreationException(ProjectCreationError::TM_KEY_INVALID, 'The TM key is not valid.');
        $this->failOnPrivateTmKeys(static function (ProjectStructure $projectStructure) use ($original): void {
            $projectStructure->id_project = 77;

            throw $original;
        });

        $thrown = $this->runCreateProject();

        $expected = [['code' => ProjectCreationError::TM_KEY_INVALID->value, 'message' => 'The TM key is not valid.']];
        self::assertSame($expected, $errorsAtDeletion);
        self::assertSame($expected, $this->pm->getTestProjectStructure()->result['errors']);
        self::assertSame(ProjectCreationError::TM_KEY_INVALID->value, $thrown->getCode());
        // A failing deletion does not replace the original failure
        self::assertSame($original, $thrown->getPrevious());
    }

    // ── xliff_parameters normalization runs inside the try ──────────

    #[Test]
    public function invalidXliffParametersRecordA400Error(): void
    {
        $this->expectNoProjectDeletion();

        // After queue deserialization xliff_parameters is a plain array; an unknown rule type is refused
        $this->pm->setProjectStructureValue('xliff_parameters', ['xliff99' => []]);

        $thrown = $this->runCreateProject();

        self::assertSame(
            [['code' => ProjectCreationError::INVALID_XLIFF_PARAMETERS->value, 'message' => 'Invalid rule: xliff99']],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertSame(ProjectCreationError::INVALID_XLIFF_PARAMETERS->value, $thrown->getCode());
        self::assertInstanceOf(Throwable::class, $thrown->getPrevious());
        self::assertSame('Invalid rule: xliff99', $thrown->getPrevious()->getMessage());
    }
    // ── Finalize: a rollback deletes the project, a failure after the commit does not ──

    /**
     * Stub every collaborator between the upload directory and the finalize, so createProject() runs
     * its whole pipeline and only the finalize touches the test database.
     */
    private function reachTheFinalize(FeatureSet $features, ProjectManagerModel $model): void
    {
        $this->pm = $this->makeProjectManager($features);
        $this->pm->setProjectStructureValue('id_project', self::MISSING_PROJECT_ID);
        $this->pm->setProjectStructureValue('ppassword', 'projectpw');
        $this->pm->setProjectStructureValue('project_name', 'committed project');
        $this->pm->setProjectStructureValue('uploadToken', 'upload');
        $this->pm->setProjectStructureValue('array_files', []);
        $this->pm->setProjectStructureValue('array_jobs', [
            'job_list' => [],
            'job_pass' => [],
            'job_segments' => [],
            'job_languages' => ['it-IT'],
            'payable_rates' => [],
        ]);

        $model->method('createProjectRecord')->willReturn(new ProjectStruct(['id' => self::MISSING_PROJECT_ID]));
        $this->pm->setProjectManagerModel($model);

        $this->pm->setTmKeyService($this->createStub(TmKeyService::class));

        $this->insertFiles([1]);

        $this->pm->setSegmentExtractor($this->extractorCounting(segments: 1, words: 1));

        $this->pm->setSegmentStorageService($this->createStub(SegmentStorageService::class));
        $this->pm->setJobCreationService($this->createStub(JobCreationService::class));
        $this->pm->setQAProcessor($this->createStub(QAProcessor::class));
        $this->pm->setProjectsMetadataDao($this->createStub(ProjectsMetadataDao::class));
    }

    /**
     * An extractor that reports these totals once the files are extracted.
     */
    private function extractorCounting(int $segments, int $words): SegmentExtractor&Stub
    {
        $extractor = $this->createStub(SegmentExtractor::class);
        $extractor->method('getTotalSegments')->willReturn($segments);
        $extractor->method('getFilesWordCount')->willReturn($words);
        $extractor->method('getShowInCattoolSegsCounter')->willReturn($segments);

        return $extractor;
    }

    /**
     * Make the file insertion return one file per id, named after it.
     *
     * @param list<int> $fileIds
     */
    private function insertFiles(array $fileIds): void
    {
        $files = [];
        foreach ($fileIds as $fid) {
            $files[$fid] = ['original_filename' => "file$fid.xliff"];
        }

        $fileInsertion = $this->createStub(FileInsertionService::class);
        $fileInsertion->method('resolveAndInsertFiles')->willReturn($files);
        $this->pm->setFileInsertionService($fileInsertion);
    }

    private function passThroughFeatures(): FeatureSet
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')->willReturnArgument(0);

        return $features;
    }

    private function failOnPostProjectCreate(Throwable $failure): FeatureSet
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')->willReturnCallback(static function (object $event) use ($failure): object {
            if ($event instanceof PostProjectCreateEvent) {
                throw $failure;
            }

            return $event;
        });

        return $features;
    }

    #[Test]
    public function aFinalizeFailureDeletesTheProjectAndRecordsTheGenericError(): void
    {
        $original = new PDOException('SQLSTATE[HY000]: General error');
        (new ReflectionProperty(Exception::class, 'code'))->setValue($original, 'HY000');

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->once())->method('deleteProject')->with(self::MISSING_PROJECT_ID);
        $this->reachTheFinalize($this->failOnPostProjectCreate($original), $model);

        $thrown = $this->runCreateProject();

        $result = $this->pm->getTestProjectStructure()->result;
        self::assertSame([['code' => ProjectCreationError::CREATION_FAILED->value, 'message' => self::GENERIC_MESSAGE]], $result['errors']);
        self::assertArrayNotHasKey('code', $result);
        self::assertArrayNotHasKey('id_project', $result);
        self::assertSame($original, $thrown->getPrevious());
        self::assertSame([], $this->pm->activityLogPushedInsideScope);
        self::assertDirectoryDoesNotExist($this->queueRoot . DIRECTORY_SEPARATOR . 'upload');
    }

    #[Test]
    public function aCommittedProjectIsReportedAndLoggedOnce(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $this->reachTheFinalize($this->passThroughFeatures(), $model);

        $this->pm->createProject();

        $result = $this->pm->getTestProjectStructure()->result;
        self::assertEmpty($result['errors']);
        self::assertSame(1, $result['code']);
        self::assertSame(self::MISSING_PROJECT_ID, $result['id_project']);
        // Pushed once, after the commit: no transaction scope was open
        self::assertSame([false], $this->pm->activityLogPushedInsideScope);
        self::assertDirectoryDoesNotExist($this->queueRoot . DIRECTORY_SEPARATOR . 'upload');
    }

    #[Test]
    public function aFailedActivityLogStillReportsTheCommittedProject(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $this->reachTheFinalize($this->passThroughFeatures(), $model);

        // An Error, not an Exception: the old catch inside pushActivityLog() let it escape with no result.
        $this->pm->activityLogFailure = new Error('Activity log failed');

        $this->pm->createProject();

        $result = $this->pm->getTestProjectStructure()->result;
        self::assertEmpty($result['errors']);
        self::assertSame(self::MISSING_PROJECT_ID, $result['id_project']);
        self::assertSame([false], $this->pm->activityLogPushedInsideScope);
    }

    #[Test]
    public function aFailureAfterTheCommitKeepsTheProject(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $this->reachTheFinalize($this->passThroughFeatures(), $model);

        $original = new Error('Analyze URL failed');
        $this->pm->analyzeUrlFailure = $original;
        $volumeAnalysis = AppConfig::$VOLUME_ANALYSIS_ENABLED;
        AppConfig::$VOLUME_ANALYSIS_ENABLED = true;

        try {
            $thrown = $this->runCreateProject();
        } finally {
            AppConfig::$VOLUME_ANALYSIS_ENABLED = $volumeAnalysis;
        }

        self::assertSame(
            [['code' => ProjectCreationError::CREATION_FAILED->value, 'message' => self::GENERIC_MESSAGE]],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertSame($original, $thrown->getPrevious());
        self::assertSame([false], $this->pm->activityLogPushedInsideScope);
        self::assertDirectoryDoesNotExist($this->queueRoot . DIRECTORY_SEPARATOR . 'upload');
    }

    // ── Extraction: the totals gate the project, a file with no text is dropped ──

    /**
     * @return array<string, array{int, int, ProjectCreationError, string}>
     */
    public static function totalsRefused(): array
    {
        return [
            'no segment'       => [0, 0, ProjectCreationError::NO_TRANSLATABLE_TEXT, 'No translatable content found in any uploaded file. The project cannot be created.'],
            'too many words'   => [1, PHP_INT_MAX, ProjectCreationError::MAX_WORDS_EXCEEDED, 'Matecat is unable to create your project. Please contact us at '],
        ];
    }

    #[Test]
    #[DataProvider('totalsRefused')]
    public function extractedTotalsOutOfBoundsRecordTheirOwnError(int $segments, int $words, ProjectCreationError $error, string $messageStart): void
    {
        $this->reachTheFinalize($this->passThroughFeatures(), $this->createStub(ProjectManagerModel::class));
        $this->pm->setSegmentExtractor($this->extractorCounting($segments, $words));

        $thrown = $this->runCreateProject();

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        self::assertCount(1, $errors);
        self::assertSame($error->value, $errors[0]['code']);
        self::assertStringStartsWith($messageStart, $errors[0]['message']);
        self::assertSame($error->value, $thrown->getCode());
    }

    #[Test]
    public function aFileWithNoTextIsDroppedWhenAnotherFileRemains(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $this->reachTheFinalize($this->passThroughFeatures(), $model);
        $this->insertFiles([1, 2]);

        $extractor = $this->extractorCounting(segments: 1, words: 1);
        $extractor->method('extract')->willReturnCallback(static function (int $fid): void {
            if ($fid === 1) {
                throw new ProjectCreationException(ProjectCreationError::NO_TRANSLATABLE_TEXT, 'No text in file1.xliff');
            }
        });
        $this->pm->setSegmentExtractor($extractor);

        // Only the file left in the structure has its segments stored
        $storage = $this->createMock(SegmentStorageService::class);
        $storage->expects($this->once())->method('storeSegments')->with(2);
        $this->pm->setSegmentStorageService($storage);

        $this->pm->createProject();

        self::assertEmpty($this->pm->getTestProjectStructure()->result['errors']);
    }

    #[Test]
    public function theLastFileWithNoTextFailsTheProject(): void
    {
        $this->reachTheFinalize($this->passThroughFeatures(), $this->createStub(ProjectManagerModel::class));
        $this->insertFiles([1, 2]);

        $extractor = $this->extractorCounting(segments: 0, words: 0);
        $extractor->method('extract')->willThrowException(
            new ProjectCreationException(ProjectCreationError::NO_TRANSLATABLE_TEXT, 'No text in this file')
        );
        $this->pm->setSegmentExtractor($extractor);

        $thrown = $this->runCreateProject();

        // The first file is dropped; the second is the last one left, so its failure is the project's
        self::assertSame(
            [['code' => ProjectCreationError::NO_TRANSLATABLE_TEXT->value, 'message' => 'No text in this file']],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertSame(ProjectCreationError::NO_TRANSLATABLE_TEXT->value, $thrown->getCode());
    }

    #[Test]
    public function anyOtherExtractionFailureFailsTheProjectEvenWithFilesLeft(): void
    {
        $this->reachTheFinalize($this->passThroughFeatures(), $this->createStub(ProjectManagerModel::class));
        $this->insertFiles([1, 2]);

        $original = new RuntimeException('Parser crashed');
        $extractor = $this->extractorCounting(segments: 1, words: 1);
        $extractor->method('extract')->willThrowException($original);
        $this->pm->setSegmentExtractor($extractor);

        $thrown = $this->runCreateProject();

        self::assertSame(
            [['code' => ProjectCreationError::CREATION_FAILED->value, 'message' => self::GENERIC_MESSAGE]],
            $this->pm->getTestProjectStructure()->result['errors']
        );
        self::assertSame($original, $thrown->getPrevious());
    }

    // ── Notes, contexts and the fast analysis file ──────────────────

    #[Test]
    public function notesAndContextGroupsAreStoredWithTheProject(): void
    {
        $notes = ['unit-1' => ['entries' => ['A note']]];
        $contextGroups = ['unit-1' => ['context_json' => '{}']];

        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $model->expects($this->once())->method('bulkInsertSegmentNotesAndMetadata')->with($notes);
        $model->expects($this->once())->method('bulkInsertContextsGroups')->with(self::MISSING_PROJECT_ID, $contextGroups);
        $this->reachTheFinalize($this->passThroughFeatures(), $model);
        $this->pm->setProjectStructureValue('notes', $notes);
        $this->pm->setProjectStructureValue('context_group', $contextGroups);

        $this->pm->createProject();

        self::assertEmpty($this->pm->getTestProjectStructure()->result['errors']);
    }

    #[Test]
    public function theFastAnalysisFileTiesEachSegmentToEveryJob(): void
    {
        $model = $this->createMock(ProjectManagerModel::class);
        $model->expects($this->never())->method('deleteProject');
        $this->reachTheFinalize($this->passThroughFeatures(), $model);
        $this->pm->setProjectStructureValue('array_jobs', [
            'job_list' => [],
            'job_pass' => [],
            'job_segments' => ['10-pass1' => [], '11-pass2' => []],
            'job_languages' => ['it-IT', 'fr-FR'],
            'payable_rates' => [],
        ]);
        $this->pm->setProjectStructureValue('source_language', 'en-US');
        $this->pm->setProjectStructureValue('segments_metadata', [
            ['id' => 5, 'segment' => 'Hello', 'internal_id' => 'u1', 'xliff_mrk_id' => null, 'show_in_cattool' => 1],
        ]);

        $this->pm->createProject();

        $file = $this->queueRoot . DIRECTORY_SEPARATOR . 'waiting_analysis_' . self::MISSING_PROJECT_ID . '.ser';
        $stored = unserialize((string)file_get_contents($file));

        self::assertCount(1, $stored);
        self::assertSame('5-10:pass1,11:pass2', $stored[0]['jsid']);
        self::assertSame('en-US', $stored[0]['source']);
        self::assertSame('it-IT,fr-FR', $stored[0]['target']);
        self::assertArrayNotHasKey('internal_id', $stored[0]);
        self::assertArrayNotHasKey('show_in_cattool', $stored[0]);
    }
}
