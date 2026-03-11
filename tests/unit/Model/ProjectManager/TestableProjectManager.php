<?php

namespace unit\Model\ProjectManager;

use ArrayObject;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectManager\ProjectManager;
use Model\Xliff\DTO\XliffRulesModel;
use ReflectionClass;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\MatecatLogger;

/**
 * A testable subclass of ProjectManager that bypasses the heavy constructor
 * and allows injection of dependencies needed by _extractSegments().
 *
 * This is needed because ProjectManager's constructor initializes DB connections,
 * TMX services, and other infrastructure that is not relevant for unit-testing
 * the segment extraction logic.
 */
class TestableProjectManager extends ProjectManager
{
    /**
     * Bypass the parent constructor entirely.
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
        // intentionally empty — we inject dependencies manually
    }

    /**
     * Initialize the testable instance with mocked/stubbed dependencies.
     */
    public function initForTest(
        MateCatFilter    $filter,
        FeatureSet       $features,
        MetadataDao      $filesMetadataDao,
        MatecatLogger    $logger,
        ?XliffRulesModel $xliffParameters = null,
    ): void {
        $this->filter           = $filter;
        $this->features         = $features;
        $this->filesMetadataDao = $filesMetadataDao;

        // Use reflection to set the private logger
        $ref        = new ReflectionClass(ProjectManager::class);
        $loggerProp = $ref->getProperty('logger');
        $loggerProp->setValue($this, $logger);

        // Initialize the projectStructure with all keys needed by _extractSegments
        $this->projectStructure = new RecursiveArrayObject([
            'id_project'             => 999,
            'source_language'        => 'en-US',
            'target_language'        => new RecursiveArrayObject(['it-IT']),
            'segments'               => new ArrayObject(),
            'segments-original-data' => new ArrayObject(),
            'segments-meta-data'     => new ArrayObject(),
            'file-part-id'           => new ArrayObject(),
            'file-metadata'          => new ArrayObject(),
            'translations'           => new ArrayObject(),
            'notes'                  => new ArrayObject(),
            'context-group'          => new ArrayObject(),
            'current-xliff-info'     => [],
            'xliff_parameters'       => $xliffParameters ?? new XliffRulesModel(),
            'result'                 => ['errors' => []],
        ]);
    }

    /**
     * Public wrapper to invoke the protected _extractSegments.
     */
    public function callExtractSegments(int $fid, array $file_info): void
    {
        $this->_extractSegments($fid, $file_info);
    }

    /**
     * Expose projectStructure for assertions.
     */
    public function getTestProjectStructure(): RecursiveArrayObject|ArrayObject
    {
        return $this->projectStructure;
    }

    /**
     * Expose counters for assertions.
     */
    public function getFilesWordCount(): int
    {
        return $this->files_word_count;
    }

    public function getShowInCattoolSegsCounter(): int
    {
        return $this->show_in_cattool_segs_counter;
    }

    public function getTotalSegments(): int
    {
        return $this->total_segments;
    }
}

