<?php

namespace Matecat\Core\Model\ProjectCreation;

use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\SegmentStorageService;
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

    /** @var int[] Project id passed with each captured persist call */
    private array $persistedSegmentMetadataProjectIds = [];

    /** @var array<array{id_segment: int, map: array}> Captured original data inserts */
    private array $insertedOriginalDataRecords = [];

    /** @var ?SegmentDao Injectable SegmentDao for tests */
    private ?SegmentDao $segmentDao = null;

    public function __construct(
        IDatabase            $dbHandler,
        FeatureSet           $features,
        MatecatLogger        $logger,
        ProjectManagerModel  $projectManagerModel,
    ) {
        parent::__construct($dbHandler, $features, $logger, $projectManagerModel);
    }

    /**
     * Override to capture calls instead of hitting the DB.
     */
    protected function persistSegmentMetadata(SegmentMetadataStruct $metadataStruct, int $id_project): void
    {
        $this->persistedSegmentMetadata[] = $metadataStruct;
        $this->persistedSegmentMetadataProjectIds[] = $id_project;
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
     * Get the project id passed with each captured segment metadata persist call.
     * @return int[]
     */
    public function getPersistedSegmentMetadataProjectIds(): array
    {
        return $this->persistedSegmentMetadataProjectIds;
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
    public function callSaveSegmentMetadata(int $id_segment, ?SegmentMetadataStruct $metadataStruct = null, int $id_project = 1): void
    {
        $this->saveSegmentMetadata($id_segment, $id_project, $metadataStruct);
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
}
