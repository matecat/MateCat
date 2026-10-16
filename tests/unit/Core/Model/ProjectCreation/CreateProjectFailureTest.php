<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TmKeyService;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Unit tests for the failure path of {@see \Model\ProjectCreation\ProjectManager::createProject()}.
 *
 * Every failure ends in the single catch block, which:
 * - deletes the records written so far when the project record exists (clearFailedProject)
 * - records exactly one error for the user, unless a validator already recorded why the project is invalid
 * - acks the message with an EndQueueException carrying the recorded code and the original as previous
 *
 * The failures are raised by collaborators reached early in the pipeline (the xliff_parameters
 * normalization, the ValidateProjectCreationEvent dispatch, TmKeyService::setPrivateTMKeys()), so the
 * upload directory is never resolved and no storage or queue is touched.
 */
class CreateProjectFailureTest extends AbstractTest
{
    private const string GENERIC_MESSAGE = 'Project creation failed. Please try again.';

    private TestableProjectManager $pm;
    private string $originalFileStorageMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $this->pm = $this->makeProjectManager($this->createStub(FeatureSet::class));
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
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

        // No project record yet: the failures below happen before createProjectRecord()
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

    // ── Enum-coded failure: its code and message reach the user ────

    #[Test]
    public function aFailureWithAProjectCreationErrorCodeRecordsThatCodeAndMessage(): void
    {
        $this->expectNoProjectDeletion();

        $original = new Exception('The TM key is not valid.', ProjectCreationError::TM_KEY_INVALID->value);
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

    #[Test]
    public function aPdoExceptionWithAnSqlStateCodeRecordsTheGenericError(): void
    {
        $this->expectNoProjectDeletion();

        $original = new PDOException("SQLSTATE[HY000]: General error: 2006 MySQL server has gone away");
        (new ReflectionProperty(Exception::class, 'code'))->setValue($original, 'HY000');

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

            throw new Exception('The TM key is not valid.', ProjectCreationError::TM_KEY_INVALID->value);
        });

        $this->runCreateProject();

        self::assertCount(1, $this->pm->getTestProjectStructure()->result['errors']);
    }

    #[Test]
    public function aFailureBeforeTheProjectRecordExistsDeletesNothing(): void
    {
        $this->expectNoProjectDeletion();

        $this->failOnPrivateTmKeys(static function (): void {
            throw new Exception('The TM key is not valid.', ProjectCreationError::TM_KEY_INVALID->value);
        });

        $this->runCreateProject();

        self::assertCount(1, $this->pm->getTestProjectStructure()->result['errors']);
    }

    #[Test]
    public function aFailingProjectDeletionDoesNotReplaceTheOriginalFailure(): void
    {
        $model = $this->createStub(ProjectManagerModel::class);
        $model->method('deleteProject')->willThrowException(new PDOException('Lock wait timeout exceeded'));
        $this->pm->setProjectManagerModel($model);

        $original = new Exception('The TM key is not valid.', ProjectCreationError::TM_KEY_INVALID->value);
        $this->failOnPrivateTmKeys(static function (ProjectStructure $projectStructure) use ($original): void {
            $projectStructure->id_project = 77;

            throw $original;
        });

        $thrown = $this->runCreateProject();

        self::assertSame(
            [['code' => ProjectCreationError::TM_KEY_INVALID->value, 'message' => 'The TM key is not valid.']],
            $this->pm->getTestProjectStructure()->result['errors']
        );
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
}
