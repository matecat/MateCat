<?php

namespace unit\Model\ProjectManager;

use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\ProjectManager\ProjectManagerModel;
use Model\ProjectManager\SegmentStorageService;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataStruct;
use Utils\Logger\MatecatLogger;

/**
 * A testable subclass of SegmentStorageService that overrides
 * persistence methods to capture calls instead of hitting the DB.
 */
class TestableSegmentStorageService extends SegmentStorageService
{
    /** @var SegmentMetadataStruct[] Captured persist calls */
    private array $persistedSegmentMetadata = [];

    /** @var array<array{id_segment: int, map: array}> Captured original data inserts */
    private array $insertedOriginalDataRecords = [];

    /** @var ?SegmentDao Injectable SegmentDao for tests */
    private ?SegmentDao $segmentDao = null;

    /** @var ?array Injectable chunk results for getChunksByJobId */
    private ?array $chunksByJobIdResult = null;

    public function __construct(
        IDatabase            $dbHandler,
        FeatureSet           $features,
        MatecatLogger        $logger,
        MateCatFilter        $filter,
        ProjectManagerModel  $projectManagerModel,
    ) {
        parent::__construct($dbHandler, $features, $logger, $filter, $projectManagerModel);
    }

    /**
     * Override to capture calls instead of hitting the DB.
     */
    protected function persistSegmentMetadata(SegmentMetadataStruct $metadataStruct): void
    {
        $this->persistedSegmentMetadata[] = $metadataStruct;
    }

    /**
     * Get captured segment metadata persist calls.
     * @return SegmentMetadataStruct[]
     */
    public function getPersistedSegmentMetadata(): array
    {
        return $this->persistedSegmentMetadata;
    }

    /**
     * Override to capture calls instead of hitting the DB.
     */
    protected function insertOriginalDataRecord(int $id_segment, array $map): void
    {
        $this->insertedOriginalDataRecords[] = ['id_segment' => $id_segment, 'map' => $map];
    }

    /**
     * Get captured original data insert calls.
     * @return array<array{id_segment: int, map: array}>
     */
    public function getInsertedOriginalDataRecords(): array
    {
        return $this->insertedOriginalDataRecords;
    }

    /**
     * Public wrapper to invoke the protected saveSegmentMetadata().
     */
    public function callSaveSegmentMetadata(int $id_segment, ?SegmentMetadataStruct $metadataStruct = null): void
    {
        $this->saveSegmentMetadata($id_segment, $metadataStruct);
    }

    /**
     * Inject a SegmentDao stub/mock for tests.
     */
    public function setSegmentDao(SegmentDao $segmentDao): void
    {
        $this->segmentDao = $segmentDao;
    }

    /**
     * Override to return the injected SegmentDao instead of creating a real one.
     */
    protected function createSegmentDao(): SegmentDao
    {
        return $this->segmentDao ?? parent::createSegmentDao();
    }

    /**
     * Inject chunk results for getChunksByJobId.
     *
     * @param JobStruct[] $chunks
     */
    public function setChunksByJobIdResult(array $chunks): void
    {
        $this->chunksByJobIdResult = $chunks;
    }

    /**
     * Override to return injected chunks instead of hitting the DB.
     */
    protected function getChunksByJobId(int $jobId): array
    {
        return $this->chunksByJobIdResult ?? parent::getChunksByJobId($jobId);
    }
}
