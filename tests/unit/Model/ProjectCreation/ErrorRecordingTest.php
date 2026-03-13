<?php

namespace unit\Model\ProjectCreation;

use DomainException;
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
 * - `_validateUploadToken()` — records error code -19 for missing/invalid tokens
 * - `_validateXliffParameters()` — records error from DomainException on invalid params
 * - `sanitizeProjectStructure()` — resets errors to empty array, then validates
 * - General error array structure (code + message keys)
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
    // _validateUploadToken() — error recording
    // =========================================================================

    #[Test]
    public function testValidateUploadTokenRecordsErrorWhenTokenMissing(): void
    {
        // uploadToken is not set in the default test projectStructure
        try {
            $this->pm->callValidateUploadToken();
            $this->fail('Expected Exception was not thrown');
        } catch (Exception) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
        $this->assertEquals('Invalid Upload Token.', $errors[0]['message']);
    }

    #[Test]
    public function testValidateUploadTokenRecordsErrorWhenTokenInvalid(): void
    {
        $this->pm->setProjectStructureValue('uploadToken', 'not-a-valid-uuid');

        try {
            $this->pm->callValidateUploadToken();
            $this->fail('Expected Exception was not thrown');
        } catch (Exception) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
    }

    #[Test]
    public function testValidateUploadTokenThrowsExceptionWithCode(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(-19);
        $this->expectExceptionMessage('Invalid Upload Token.');

        $this->pm->callValidateUploadToken();
    }

    #[Test]
    public function testValidateUploadTokenDoesNotRecordErrorWhenTokenValid(): void
    {
        // A valid UUID token
        $this->pm->setProjectStructureValue('uploadToken', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890');

        // Should not throw
        $this->pm->callValidateUploadToken();

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(0, $errors);
    }

    // =========================================================================
    // _validateXliffParameters() — error recording
    // =========================================================================

    #[Test]
    public function testValidateXliffParametersRecordsErrorForInvalidType(): void
    {
        // Set xliff_parameters to a string (not an array or ArrayObject)
        $this->pm->setProjectStructureValue('xliff_parameters', 'invalid-string');

        try {
            $this->pm->callValidateXliffParameters();
            $this->fail('Expected DomainException was not thrown');
        } catch (DomainException) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertEquals(400, $errors[0]['code']);
        $this->assertEquals('Invalid xliff_parameters value found.', $errors[0]['message']);
    }

    #[Test]
    public function testValidateXliffParametersRethrowsDomainException(): void
    {
        $this->pm->setProjectStructureValue('xliff_parameters', 'invalid-string');

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Invalid xliff_parameters value found.');

        $this->pm->callValidateXliffParameters();
    }

    #[Test]
    public function testValidateXliffParametersDoesNotRecordErrorWhenValid(): void
    {
        // _validateXliffParameters expects an ArrayObject or array, not XliffRulesModel
        // (it calls XliffRulesModel::fromArrayObject() internally)
        $this->pm->setProjectStructureValue('xliff_parameters', []);

        // Should not throw
        $this->pm->callValidateXliffParameters();

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(0, $errors);
    }

    // =========================================================================
    // sanitizeProjectStructure() — error reset + validation
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testSanitizeProjectStructureResetsErrorsToArrayObject(): void
    {
        // Pre-populate errors with a plain array entry
        $ps = $this->pm->getTestProjectStructure();
        $ps['result']['errors'][] = ['code' => -999, 'message' => 'pre-existing error'];

        // Add required keys for sanitizeProjectStructure
        $this->pm->setProjectStructureValue('uploadToken', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890');
        $this->pm->setProjectStructureValue('xliff_parameters', []);
        $this->pm->setProjectStructureValue('project_features', []);

        $this->pm->sanitizeProjectStructure();

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];

        // Errors should have been reset to a fresh empty array (pre-existing error is gone)
        $this->assertIsArray($errors);
        $this->assertCount(0, $errors);
    }

    #[Test]
    public function testSanitizeProjectStructureRecordsErrorOnInvalidToken(): void
    {
        // No uploadToken set — should fail on _validateUploadToken
        $this->pm->setProjectStructureValue('project_features', []);

        try {
            $this->pm->sanitizeProjectStructure();
            $this->fail('Expected Exception was not thrown');
        } catch (Exception) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];

        // Errors should be an array (reset happened before validation)
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSanitizeProjectStructureRecordsErrorOnInvalidXliffParams(): void
    {
        // Valid token but invalid xliff_parameters
        $this->pm->setProjectStructureValue('uploadToken', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890');
        $this->pm->setProjectStructureValue('xliff_parameters', 42);
        $this->pm->setProjectStructureValue('project_features', []);

        try {
            $this->pm->sanitizeProjectStructure();
            $this->fail('Expected DomainException was not thrown');
        } catch (DomainException) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals(400, $errors[0]['code']);
    }

    // =========================================================================
    // Error entry structure
    // =========================================================================

    #[Test]
    public function testErrorEntryHasCodeAndMessageKeys(): void
    {
        try {
            $this->pm->callValidateUploadToken();
        } catch (Exception) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $error = $errors[0];

        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
        // Exactly two keys — no extra fields
        $this->assertCount(2, $error);
    }

    #[Test]
    public function testMultipleErrorsAreAppended(): void
    {
        // First error: invalid token
        try {
            $this->pm->callValidateUploadToken();
        } catch (Exception) {
            // expected
        }

        // Manually append a second error using arrow syntax (offsetGet returns by value)
        $ps = $this->pm->getTestProjectStructure();
        $ps->result['errors'][] = ['code' => -999, 'message' => 'Second error'];

        $errors = $ps->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
        $this->assertEquals(-999, $errors[1]['code']);
    }
}
