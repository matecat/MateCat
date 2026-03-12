<?php

namespace unit\Model\ProjectManager;

use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\ProjectManager\JobSplitMergeService;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Translators\TranslatorsModel;
use Model\Users\UserDao;
use Model\WordCount\CounterModel;
use Utils\Logger\MatecatLogger;
use Utils\Shop\Cart;

/**
 * A testable subclass of JobSplitMergeService that allows injection
 * of mocked dependencies for unit testing without DB access.
 */
class TestableJobSplitMergeService extends JobSplitMergeService
{
    private ?JobDao $jobDaoOverride = null;
    private ?JobStruct $jobByIdAndPasswordOverride = null;
    private ?Cart $cartOverride = null;
    private ?CounterModel $counterModelOverride = null;
    private ?ProjectDao $projectDaoOverride = null;
    private ?ProjectsMetadataDao $projectsMetadataDaoOverride = null;
    private ?TranslatorsModel $translatorsModelOverride = null;
    private ?UserDao $userDaoOverride = null;

    /** @var string[] Generated random strings queue */
    private array $randomStrings = [];
    private int $randomStringIndex = 0;

    private bool $beginTransactionCalled = false;
    private bool $destroyAnalysisCacheCalled = false;
    private ?int $destroyAnalysisCacheProjectId = null;

    /** @var array{queue: string, workerClass: string, data: array}[] */
    private array $enqueuedWorkers = [];

    /** @var array{job: JobStruct, newPassword: string|false}[] */
    private array $updateForMergeCalls = [];
    /** @var JobStruct[] */
    private array $deleteOnMergeCalls = [];

    /** @var array<string, mixed>[] Owner keys to return */
    private array $ownerKeysOverride = [];
    private bool $ownerKeysThrows = false;
    private ?ProjectStruct $projectForCacheOverride = null;

    public function __construct(
        IDatabase     $dbHandler,
        FeatureSet    $features,
        MatecatLogger $logger,
    ) {
        parent::__construct($dbHandler, $features, $logger);
    }

    // ── JobDao ──

    public function setJobDao(JobDao $dao): void
    {
        $this->jobDaoOverride = $dao;
    }

    protected function createJobDao(): JobDao
    {
        return $this->jobDaoOverride ?? parent::createJobDao();
    }

    // ── getJobByIdAndPassword ──

    public function setJobByIdAndPassword(?JobStruct $job): void
    {
        $this->jobByIdAndPasswordOverride = $job;
    }

    protected function getJobByIdAndPassword(int $id, string $password): ?JobStruct
    {
        return $this->jobByIdAndPasswordOverride ?? parent::getJobByIdAndPassword($id, $password);
    }

    // ── beginTransaction ──

    protected function beginTransaction(): void
    {
        $this->beginTransactionCalled = true;
    }

    public function wasBeginTransactionCalled(): bool
    {
        return $this->beginTransactionCalled;
    }

    // ── Cart ──

    public function setCart(Cart $cart): void
    {
        $this->cartOverride = $cart;
    }

    protected function getCart(): Cart
    {
        return $this->cartOverride ?? parent::getCart();
    }

    // ── CounterModel ──

    public function setCounterModel(CounterModel $model): void
    {
        $this->counterModelOverride = $model;
    }

    protected function createCounterModel(): CounterModel
    {
        return $this->counterModelOverride ?? parent::createCounterModel();
    }

    // ── ProjectDao ──

    public function setProjectDao(ProjectDao $dao): void
    {
        $this->projectDaoOverride = $dao;
    }

    protected function createProjectDao(): ProjectDao
    {
        return $this->projectDaoOverride ?? parent::createProjectDao();
    }

    // ── ProjectsMetadataDao ──

    public function setProjectsMetadataDao(ProjectsMetadataDao $dao): void
    {
        $this->projectsMetadataDaoOverride = $dao;
    }

    protected function createProjectsMetadataDao(): ProjectsMetadataDao
    {
        return $this->projectsMetadataDaoOverride ?? parent::createProjectsMetadataDao();
    }

    // ── TranslatorsModel ──

    public function setTranslatorsModel(TranslatorsModel $model): void
    {
        $this->translatorsModelOverride = $model;
    }

    protected function createTranslatorsModel(JobStruct $job): TranslatorsModel
    {
        return $this->translatorsModelOverride ?? parent::createTranslatorsModel($job);
    }

    // ── UserDao ──

    public function setUserDao(UserDao $dao): void
    {
        $this->userDaoOverride = $dao;
    }

    protected function createUserDao(): UserDao
    {
        return $this->userDaoOverride ?? parent::createUserDao();
    }

    // ── Random strings ──

    /**
     * Queue deterministic random strings for testing.
     * @param string[] $strings
     */
    public function setRandomStrings(array $strings): void
    {
        $this->randomStrings = $strings;
        $this->randomStringIndex = 0;
    }

    protected function generateRandomString(): string
    {
        if (!empty($this->randomStrings) && $this->randomStringIndex < count($this->randomStrings)) {
            return $this->randomStrings[$this->randomStringIndex++];
        }

        return 'test_random_' . $this->randomStringIndex++;
    }

    // ── AnalysisDao cache destroy ──

    protected function destroyAnalysisCacheByProjectId(int $projectId): void
    {
        $this->destroyAnalysisCacheCalled = true;
        $this->destroyAnalysisCacheProjectId = $projectId;
    }

    public function wasDestroyAnalysisCacheCalled(): bool
    {
        return $this->destroyAnalysisCacheCalled;
    }

    public function getDestroyAnalysisCacheProjectId(): ?int
    {
        return $this->destroyAnalysisCacheProjectId;
    }

    // ── WorkerClient enqueue ──

    protected function enqueueWorker(string $queue, string $workerClass, array $data): void
    {
        $this->enqueuedWorkers[] = ['queue' => $queue, 'workerClass' => $workerClass, 'data' => $data];
    }

    /**
     * @return array{queue: string, workerClass: string, data: array}[]
     */
    public function getEnqueuedWorkers(): array
    {
        return $this->enqueuedWorkers;
    }

    // ── updateForMerge / deleteOnMerge ──

    protected function updateForMerge(JobStruct $job, string $newPassword): void
    {
        $this->updateForMergeCalls[] = ['job' => $job, 'newPassword' => $newPassword];
    }

    /**
     * @return array{job: JobStruct, newPassword: string}[]
     */
    public function getUpdateForMergeCalls(): array
    {
        return $this->updateForMergeCalls;
    }

    protected function deleteOnMerge(JobStruct $job): void
    {
        $this->deleteOnMergeCalls[] = $job;
    }

    /**
     * @return JobStruct[]
     */
    public function getDeleteOnMergeCalls(): array
    {
        return $this->deleteOnMergeCalls;
    }

    // ── getOwnerKeys ──

    /**
     * @param array $keys Owner keys to return. Each element should have a 'key' and other TmKeyStruct fields.
     */
    public function setOwnerKeysResult(array $keys): void
    {
        $this->ownerKeysOverride = $keys;
    }

    public function setOwnerKeysThrows(bool $throws): void
    {
        $this->ownerKeysThrows = $throws;
    }

    protected function getOwnerKeys(array $tmKeys): array
    {
        if ($this->ownerKeysThrows) {
            throw new \Exception('TmKeyManager error');
        }

        return $this->ownerKeysOverride;
    }

    // ── getProjectForCacheInvalidation ──

    public function setProjectForCacheInvalidation(ProjectStruct $project): void
    {
        $this->projectForCacheOverride = $project;
    }

    protected function getProjectForCacheInvalidation(JobStruct $job): ProjectStruct
    {
        return $this->projectForCacheOverride ?? parent::getProjectForCacheInvalidation($job);
    }
}
