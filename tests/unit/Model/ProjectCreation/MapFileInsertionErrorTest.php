<?php

namespace unit\Model\ProjectCreation;

use Closure;
use Exception;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\FileInsertionService::mapFileInsertionError()}.
 *
 * Verifies that different error codes are mapped to the correct project error
 * entries (code + message).
 */
class MapFileInsertionErrorTest extends AbstractTest
{
    private TestableFileInsertionService $service;
    private ProjectStructure $projectStructure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TestableFileInsertionService(
            $this->createStub(ProjectManagerModel::class),
            $this->createStub(MetadataDao::class),
            null, // no GDrive session
            Closure::fromCallable(function (string $fileName): void {}),
            $this->createStub(MatecatLogger::class),
        );

        $this->projectStructure = new ProjectStructure([
            'result' => ['errors' => []],
        ]);
    }

    // ── Error code mapping tests ────────────────────────────────────

    #[Test]
    public function code10UsesExceptionMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('Original error -10', -10));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-10, $errors[0]['code']);
        $this->assertSame('Original error -10', $errors[0]['message']);
    }

    #[Test]
    public function code11UsesFixedPermissionDeniedMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('Something', -11));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-11, $errors[0]['code']);
        $this->assertSame('Failed to store reference files on disk. Permission denied', $errors[0]['message']);
    }

    #[Test]
    public function code12UsesFixedDatabaseMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('DB Error', -12));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-12, $errors[0]['code']);
        $this->assertSame('Failed to store reference files in database', $errors[0]['message']);
    }

    #[Test]
    public function code6UsesExceptionMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('Not found message', -6));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-6, $errors[0]['code']);
        $this->assertSame('Not found message', $errors[0]['message']);
    }

    #[Test]
    public function code3MapsToCode16WithFixedMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('XLIFF not found', -3));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-16, $errors[0]['code']);
        $this->assertSame('File not found. Failed to save XLIFF conversion on disk.', $errors[0]['message']);
    }

    #[Test]
    public function code13UsesExceptionMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('Custom -13 error', -13));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-13, $errors[0]['code']);
        $this->assertSame('Custom -13 error', $errors[0]['message']);
    }

    #[Test]
    public function code200UsesExceptionMessage(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('Move failed', -200));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-200, $errors[0]['code']);
        $this->assertSame('Move failed', $errors[0]['message']);
    }

    #[Test]
    public function code0WithInvalidCopySourceEncodingMapsTo200(): void
    {
        $msg = 'Something went wrong: <Message>Invalid copy source encoding.</Message>';
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception($msg, 0));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-200, $errors[0]['code']);
        $this->assertStringContainsString('rename your file(s)', $errors[0]['message']);
    }

    #[Test]
    public function code0WithoutSpecialMessageAddsGenericError(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('generic error', 0));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(0, $errors[0]['code']);
        $this->assertStringContainsString('generic error', $errors[0]['message']);
    }

    #[Test]
    public function unknownCodeAddsGenericError(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('unknown', -999));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-999, $errors[0]['code']);
        $this->assertStringContainsString('unknown', $errors[0]['message']);
    }

    #[Test]
    public function worksWithThrowableThatIsNotException(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new RuntimeException('Runtime -10', -10));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-10, $errors[0]['code']);
    }

    #[Test]
    public function multipleCallsAccumulateErrors(): void
    {
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('First', -10));
        $this->service->callMapFileInsertionError($this->projectStructure, new Exception('Second', -6));

        $errors = $this->projectStructure->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertSame(-10, $errors[0]['code']);
        $this->assertSame(-6, $errors[1]['code']);
    }
}
