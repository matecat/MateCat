<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\SegmentExtractor;
use Model\Segments\SegmentMetadataMapper;
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
        parent::__construct($config, $filter, $features, $filesMetadataDao, $segmentMetadataMapper ?? new SegmentMetadataMapper(), $logger);
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
}
