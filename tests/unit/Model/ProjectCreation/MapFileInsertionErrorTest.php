<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::mapFileInsertionError()}.
 *
 * Verifies that different error codes are mapped to the correct project error
 * entries (code + message).
 */
class MapFileInsertionErrorTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    // ── Error code mapping tests ────────────────────────────────────

    #[Test]
    public function code10UsesExceptionMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('Original error -10', -10));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-10, $errors[0]['code']);
        $this->assertSame('Original error -10', $errors[0]['message']);
    }

    #[Test]
    public function code11UsesFixedPermissionDeniedMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('Something', -11));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-11, $errors[0]['code']);
        $this->assertSame('Failed to store reference files on disk. Permission denied', $errors[0]['message']);
    }

    #[Test]
    public function code12UsesFixedDatabaseMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('DB Error', -12));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-12, $errors[0]['code']);
        $this->assertSame('Failed to store reference files in database', $errors[0]['message']);
    }

    #[Test]
    public function code6UsesExceptionMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('Not found message', -6));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-6, $errors[0]['code']);
        $this->assertSame('Not found message', $errors[0]['message']);
    }

    #[Test]
    public function code3MapsToCode16WithFixedMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('XLIFF not found', -3));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-16, $errors[0]['code']);
        $this->assertSame('File not found. Failed to save XLIFF conversion on disk.', $errors[0]['message']);
    }

    #[Test]
    public function code13UsesExceptionMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('Custom -13 error', -13));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-13, $errors[0]['code']);
        $this->assertSame('Custom -13 error', $errors[0]['message']);
    }

    #[Test]
    public function code200UsesExceptionMessage(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('Move failed', -200));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-200, $errors[0]['code']);
        $this->assertSame('Move failed', $errors[0]['message']);
    }

    #[Test]
    public function code0WithInvalidCopySourceEncodingMapsTo200(): void
    {
        $msg = 'Something went wrong: <Message>Invalid copy source encoding.</Message>';
        $this->pm->callMapFileInsertionError(new Exception($msg, 0));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-200, $errors[0]['code']);
        $this->assertStringContainsString('rename your file(s)', $errors[0]['message']);
    }

    #[Test]
    public function code0WithoutSpecialMessageAddsGenericError(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('generic error', 0));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(0, $errors[0]['code']);
        $this->assertStringContainsString('generic error', $errors[0]['message']);
    }

    #[Test]
    public function unknownCodeAddsGenericError(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('unknown', -999));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-999, $errors[0]['code']);
        $this->assertStringContainsString('unknown', $errors[0]['message']);
    }

    #[Test]
    public function worksWithThrowableThatIsNotException(): void
    {
        $this->pm->callMapFileInsertionError(new RuntimeException('Runtime -10', -10));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-10, $errors[0]['code']);
    }

    #[Test]
    public function multipleCallsAccumulateErrors(): void
    {
        $this->pm->callMapFileInsertionError(new Exception('First', -10));
        $this->pm->callMapFileInsertionError(new Exception('Second', -6));

        $errors = $this->pm->getTestProjectStructure()->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertSame(-10, $errors[0]['code']);
        $this->assertSame(-6, $errors[1]['code']);
    }
}
