<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\SegmentExtractor;
use Model\Segments\SegmentMetadataMapper;
use Utils\Engines\MyMemory;
use Utils\Logger\MatecatLogger;

/**
 * A testable subclass of SegmentExtractor that exposes protected methods
 * and allows injecting stubs for dependencies used by detectPreTranslation().
 */
class TestableSegmentExtractor extends SegmentExtractor
{
    public function __construct(
        ProjectStructure $config,
        MateCatFilter $filter,
        FeatureSet $features,
        MetadataDao $filesMetadataDao,
        MatecatLogger $logger,
        ?SegmentMetadataMapper $segmentMetadataMapper = null,
    ) {
        parent::__construct($config, $filter, $features, $filesMetadataDao, $segmentMetadataMapper ?? new SegmentMetadataMapper(), obtainTestDatabase(), $logger);
    }

    /**
     * Public wrapper for the protected detectPreTranslation().
     * @throws Exception
     */
    public function callDetectPreTranslation(
        string $sourceRawContent,
        string $targetRawContent,
        array $xliff_trans_unit,
        int $fid,
        ?int $position,
        ProjectStructure $projectStructure,
    ): ?array {
        return $this->detectPreTranslation(
            $sourceRawContent,
            $targetRawContent,
            $xliff_trans_unit,
            $fid,
            $position,
            $projectStructure,
        );
    }

    /**
     * Public wrapper for the protected manageAlternativeTranslations().
     *
     * @param array<string, mixed> $xliff_trans_unit
     * @param array<string, mixed>|null $xliff_file_attributes
     *
     * @throws Exception
     */
    public function callManageAlternativeTranslations(array $xliff_trans_unit, ?array $xliff_file_attributes): void
    {
        $this->manageAlternativeTranslations($xliff_trans_unit, $xliff_file_attributes);
    }

    private ?MyMemory $stubEngine = null;

    /**
     * Inject a stub engine so manageAlternativeTranslations() can run without a
     * database-backed EnginesFactory lookup.
     */
    public function setStubEngine(MyMemory $engine): void
    {
        $this->stubEngine = $engine;
    }

    protected function getPrivateTmEngine(): MyMemory
    {
        return $this->stubEngine ?? parent::getPrivateTmEngine();
    }
}
