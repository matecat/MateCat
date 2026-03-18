<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for the error recording pattern in {@see \Model\ProjectCreation\ProjectManager}.
 *
 * Tests cover:
 * - `addProjectError()` — appends structured error entries to projectStructure->result['errors']
 * - General error array structure (code + message keys)
 *
 * Note: `sanitizeProjectStructure()`, `validateUploadToken()`, and `validateXliffParameters()`
 * were removed as part of the controller-layer validation refactoring.
 * Upload token validation now lives in the controllers (NewController, CreateProjectController).
 * XliffRulesModel normalization is done inline at the top of `createProject()`.
 *
 * @see REFACTORING_PLAN.md — Step 0e
 */
class ErrorRecordingTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $originalFileStorageMethod;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->originalFileStorageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $featureSet = new FeatureSet();
        /** @var MateCatFilter $filter */
        $filter = MateCatFilter::getInstance($featureSet, 'en-US', 'it-IT');
        $metadataDao = $this->createStub(MetadataDao::class);
        $logger = $this->createStub(MatecatLogger::class);

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest($filter, $featureSet, $metadataDao, $logger);
    }

    public function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->originalFileStorageMethod;
        parent::tearDown();
    }

    // =========================================================================
    // addProjectError() — error recording
    // =========================================================================

    #[Test]
    public function testAddProjectErrorAppendsToResultErrors(): void
    {
        $this->pm->callAddProjectError(-19, 'Invalid Upload Token.');

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
        $this->assertEquals('Invalid Upload Token.', $errors[0]['message']);
    }

    #[Test]
    public function testAddProjectErrorDoesNotResetExistingErrors(): void
    {
        // Pre-populate with an error
        $this->pm->callAddProjectError(-999, 'First error');

        // Add another error — should NOT reset the first one
        $this->pm->callAddProjectError(400, 'Second error');

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertEquals(-999, $errors[0]['code']);
        $this->assertEquals(400, $errors[1]['code']);
    }

    // =========================================================================
    // Error entry structure
    // =========================================================================

    #[Test]
    public function testErrorEntryHasCodeAndMessageKeys(): void
    {
        $this->pm->callAddProjectError(-19, 'Invalid Upload Token.');

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $error = $errors[0];

        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
        // Exactly two keys — no extra fields
        $this->assertCount(2, $error);
    }

    #[Test]
    public function testMultipleErrorsAreAppended(): void
    {
        // First error
        $this->pm->callAddProjectError(-19, 'Invalid Upload Token.');

        // Second error appended manually
        $ps = $this->pm->getTestProjectStructure();
        $ps->result['errors'][] = ['code' => -999, 'message' => 'Second error'];

        $errors = $ps->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
        $this->assertEquals(-999, $errors[1]['code']);
    }
}
