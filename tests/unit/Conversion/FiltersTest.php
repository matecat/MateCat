<?php

namespace unit\Conversion;

use CURLFile;
use Exception;
use Model\Conversion\Filters;
use Model\Filters\DTO\IDto;
use Model\Jobs\JobStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\Registry\AppConfig;

/**
 * Testable subclass overriding external dependencies:
 * - sendToFilters() → stubs HTTP responses
 * - createLogConnection() → returns in-memory SQLite or mock PDO
 * - backupFailedConversion() → tracks calls without filesystem
 */
class TestableFilters extends Filters
{
    /** @var array<int|string, array<string, mixed>> */
    public array $stubbedResponse = [];

    /** @var array<int, array{dataGroups: array<int|string, array<string, mixed>>, endpoint: string}> */
    public array $sendToFiltersCalls = [];

    public ?PDO $mockPdo = null;

    /** @var array<int, string> */
    public array $backupCalls = [];

    protected function sendToFilters(array $dataGroups, string $endpoint): array
    {
        $this->sendToFiltersCalls[] = ['dataGroups' => $dataGroups, 'endpoint' => $endpoint];

        return $this->stubbedResponse;
    }

    protected function createLogConnection(): PDO
    {
        if ($this->mockPdo !== null) {
            return $this->mockPdo;
        }

        throw new Exception('No mock PDO configured');
    }

    protected function backupFailedConversion(string &$sentFile): void
    {
        $this->backupCalls[] = $sentFile;
    }

    /**
     * Expose protected method for testing.
     *
     * @param array<string> $headers
     *
     * @return array{instanceAddress: string, instanceVersion: string}|null
     */
    public function testExtractInstanceInfoFromHeaders(array $headers): ?array
    {
        return $this->extractInstanceInfoFromHeaders($headers);
    }

    /**
     * Expose protected method for testing.
     */
    public function testFormatErrorMessage(string $error): string
    {
        return $this->formatErrorMessage($error);
    }
}

class FiltersTest extends TestCase
{
    private TestableFilters $filters;

    private ?string $originalStorageDir = null;
    private bool $originalFiltersEmailFailures = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStorageDir = AppConfig::$STORAGE_DIR;
        $this->originalFiltersEmailFailures = AppConfig::$FILTERS_EMAIL_FAILURES;

        $this->filters = new TestableFilters();

        // Ensure AppConfig statics are set for tests that reference them
        AppConfig::$FILTERS_EMAIL_FAILURES = false;
        AppConfig::$STORAGE_DIR = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        AppConfig::$STORAGE_DIR = $this->originalStorageDir;
        AppConfig::$FILTERS_EMAIL_FAILURES = $this->originalFiltersEmailFailures;

        parent::tearDown();
    }

    // ── extractInstanceInfoFromHeaders ──────────────────────────────────

    #[Test]
    public function extractInstanceInfoReturnsAddressAndVersion(): void
    {
        $headers = [
            'Content-Type: application/json',
            'Filters-Instance: address=10.0.0.1:8080; version=2.4.3',
        ];

        $result = $this->filters->testExtractInstanceInfoFromHeaders($headers);

        self::assertNotNull($result);
        self::assertSame('10.0.0.1:8080', $result['instanceAddress']);
        self::assertSame('2.4.3', $result['instanceVersion']);
    }

    #[Test]
    public function extractInstanceInfoReturnsNullWhenHeaderMissing(): void
    {
        $headers = [
            'Content-Type: application/json',
            'X-Request-Id: abc123',
        ];

        self::assertNull($this->filters->testExtractInstanceInfoFromHeaders($headers));
    }

    #[Test]
    public function extractInstanceInfoReturnsNullForEmptyHeaders(): void
    {
        self::assertNull($this->filters->testExtractInstanceInfoFromHeaders([]));
    }

    #[Test]
    public function extractInstanceInfoReturnsFirstMatch(): void
    {
        $headers = [
            'Filters-Instance: address=first.host; version=1.0',
            'Filters-Instance: address=second.host; version=2.0',
        ];

        $result = $this->filters->testExtractInstanceInfoFromHeaders($headers);
        self::assertNotNull($result);
        self::assertSame('first.host', $result['instanceAddress']);
        self::assertSame('1.0', $result['instanceVersion']);
    }

    // ── formatErrorMessage ─────────────────────────────────────────────

    #[Test]
    public function formatErrorMessageStripsExcelExceptionPrefix(): void
    {
        $raw = 'net.translated.matecat.filters.ExtendedExcelException: Sheet1 has invalid format';
        self::assertSame('Sheet1 has invalid format', $this->filters->testFormatErrorMessage($raw));
    }

    #[Test]
    public function formatErrorMessageLeavesOtherErrorsUntouched(): void
    {
        $raw = 'Some other error message';
        self::assertSame('Some other error message', $this->filters->testFormatErrorMessage($raw));
    }

    // ── sourceToXliff ──────────────────────────────────────────────────

    #[Test]
    public function sourceToXliffDelegatesToSendToFilters(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-filter-');
        file_put_contents($tmpFile, 'test content');

        try {
            $this->filters->stubbedResponse = [
                ['successful' => true, 'document' => base64_encode('xliff content')],
            ];

            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertCount(1, $this->filters->sendToFiltersCalls);
            $call = $this->filters->sendToFiltersCalls[0];
            self::assertSame(Filters::SOURCE_TO_XLIFF_ENDPOINT, $call['endpoint']);
            self::assertCount(1, $call['dataGroups']);

            $data = $call['dataGroups'][0];
            self::assertInstanceOf(CURLFile::class, $data['document']);
            self::assertArrayHasKey('sourceLocale', $data);
            self::assertArrayHasKey('targetLocale', $data);
            self::assertArrayHasKey('extractionParams', $data);
            self::assertNull($data['segmentation']);

            // Default: segment_icu = true (icu_enabled=false)
            $extractionParams = json_decode($data['extractionParams'], true);
            self::assertTrue($extractionParams['segment_icu']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sourceToXliffWithIcuEnabledSetsSegmentIcuFalse(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-filter-');
        file_put_contents($tmpFile, 'test');

        try {
            $this->filters->stubbedResponse = [['successful' => true]];

            $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT', null, null, true);

            $data = $this->filters->sendToFiltersCalls[0]['dataGroups'][0];
            $params = json_decode($data['extractionParams'], true);
            self::assertFalse($params['segment_icu']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sourceToXliffWithLegacyIcuOverridesExtractionParams(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-filter-');
        file_put_contents($tmpFile, 'test');

        try {
            $dto = $this->createStub(IDto::class);
            $dto->method('jsonSerialize')->willReturn(['some_key' => 'value']);

            $this->filters->stubbedResponse = [['successful' => true]];

            $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT', null, $dto, false, true);

            $data = $this->filters->sendToFiltersCalls[0]['dataGroups'][0];
            $params = json_decode($data['extractionParams'], true);
            // legacy_icu = true overrides everything with escape_icu
            self::assertTrue($params['escape_icu']);
            self::assertArrayNotHasKey('some_key', $params);
            self::assertArrayNotHasKey('segment_icu', $params);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sourceToXliffWithDtoMergesExtractionParams(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-filter-');
        file_put_contents($tmpFile, 'test');

        try {
            $dto = $this->createStub(IDto::class);
            $dto->method('jsonSerialize')->willReturn(['custom_key' => 'custom_value']);

            $this->filters->stubbedResponse = [['successful' => true]];

            $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT', null, $dto);

            $data = $this->filters->sendToFiltersCalls[0]['dataGroups'][0];
            $params = json_decode($data['extractionParams'], true);
            self::assertSame('custom_value', $params['custom_key']);
            self::assertTrue($params['segment_icu']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sourceToXliffReturnsFirstResponse(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-filter-');
        file_put_contents($tmpFile, 'test');

        try {
            $expected = ['successful' => true, 'data' => 'xliff_data'];
            $this->filters->stubbedResponse = [$expected];

            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertSame($expected, $result);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ── xliffToTarget ──────────────────────────────────────────────────

    #[Test]
    public function xliffToTargetCreatesTemporaryFilesAndDelegates(): void
    {
        $this->filters->stubbedResponse = [
            'file1' => ['successful' => true, 'document_content' => 'translated'],
        ];

        $xliffsData = [
            'file1' => ['document_content' => '<xliff>test</xliff>'],
        ];

        $result = $this->filters->xliffToTarget($xliffsData);

        self::assertCount(1, $this->filters->sendToFiltersCalls);
        $call = $this->filters->sendToFiltersCalls[0];
        self::assertSame(Filters::XLIFF_TO_TARGET_ENDPOINT, $call['endpoint']);

        // The data group should contain a CURLFile for the xliff
        self::assertArrayHasKey('file1', $call['dataGroups']);
        self::assertInstanceOf(CURLFile::class, $call['dataGroups']['file1']['xliff']);
    }

    #[Test]
    public function xliffToTargetCleansUpTempFiles(): void
    {
        $this->filters->stubbedResponse = [
            0 => ['successful' => true],
        ];

        $xliffsData = [
            0 => ['document_content' => '<xliff>content</xliff>'],
        ];

        // After xliffToTarget, temp files should be deleted
        $this->filters->xliffToTarget($xliffsData);

        // Verify: the CURLFile was created from a temp file that no longer exists
        $call = $this->filters->sendToFiltersCalls[0];
        $curlFile = $call['dataGroups'][0]['xliff'];
        self::assertInstanceOf(CURLFile::class, $curlFile);
        // The temp file should have been deleted after sendToFilters
        self::assertFileDoesNotExist($curlFile->getFilename());
    }

    // ── logConversionToXliff ───────────────────────────────────────────

    #[Test]
    public function logConversionToXliffDelegatesToLogConversion(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-log-');
        file_put_contents($tmpFile, 'test content');

        try {
            $pdo = $this->createMock(PDO::class);
            $stmt = $this->createMock(PDOStatement::class);
            $pdo->expects(self::once())->method('prepare')->willReturn($stmt);
            $stmt->expects(self::once())->method('execute');

            $this->filters->mockPdo = $pdo;

            $response = [
                'successful' => true,
                'time' => 150,
                'instanceAddress' => '10.0.0.1',
                'instanceVersion' => '2.4.3',
            ];

            $this->filters->logConversionToXliff($response, $tmpFile, 'en-US', 'it-IT', null, null);

            // No backup should happen for successful conversions
            self::assertEmpty($this->filters->backupCalls);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function logConversionToXliffBacksUpOnFailure(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-log-');
        file_put_contents($tmpFile, 'test content');

        try {
            $stmt = $this->createStub(PDOStatement::class);
            $pdo = $this->createStub(PDO::class);
            $pdo->method('prepare')->willReturn($stmt);

            $this->filters->mockPdo = $pdo;

            $response = [
                'successful' => false,
                'time' => 500,
                'errorMessage' => 'Conversion failed',
            ];

            $this->filters->logConversionToXliff($response, $tmpFile, 'en-US', 'it-IT', null, null);

            self::assertCount(1, $this->filters->backupCalls);
            self::assertSame($tmpFile, $this->filters->backupCalls[0]);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function logConversionToXliffHandlesDbConnectionFailure(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-log-');
        file_put_contents($tmpFile, 'test content');

        try {
            // No mock PDO → createLogConnection() will throw
            $this->filters->mockPdo = null;

            $response = [
                'successful' => true,
                'time' => 100,
            ];

            // Should not throw — the exception is caught and logged
            $this->filters->logConversionToXliff($response, $tmpFile, 'en-US', 'it-IT', null, null);

            // No backup since we returned early
            self::assertEmpty($this->filters->backupCalls);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ── logConversionToTarget ──────────────────────────────────────────

    #[Test]
    public function logConversionToTargetDelegatesToLogConversion(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-log-');
        file_put_contents($tmpFile, 'xliff content');

        try {
            $pdo = $this->createMock(PDO::class);
            $stmt = $this->createMock(PDOStatement::class);
            $pdo->expects(self::once())->method('prepare')->willReturn($stmt);
            $stmt->expects(self::once())->method('execute');

            $this->filters->mockPdo = $pdo;

            $jobStruct = $this->createStub(JobStruct::class);
            $jobStruct->method('toArray')->willReturn([
                'source' => 'en-US',
                'target' => 'it-IT',
                'id' => 123,
                'password' => 'abc',
                'owner' => 'user@test.com',
            ]);

            $response = [
                'successful' => true,
                'time' => 200,
            ];

            $sourceFileData = [
                'id_file' => 456,
                'filename' => 'document.docx',
                'mime_type' => 'docx',
                'sha1_original_file' => 'abc123',
                'segmentation_rule' => null,
            ];

            $this->filters->logConversionToTarget($response, $tmpFile, $jobStruct, $sourceFileData);

            self::assertEmpty($this->filters->backupCalls);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function logConversionToTargetHandlesInsertException(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test-log-');
        file_put_contents($tmpFile, 'xliff content');

        try {
            $stmt = $this->createStub(PDOStatement::class);
            $stmt->method('execute')->willThrowException(new Exception('Insert failed'));
            $pdo = $this->createStub(PDO::class);
            $pdo->method('prepare')->willReturn($stmt);

            $this->filters->mockPdo = $pdo;

            $jobStruct = $this->createStub(JobStruct::class);
            $jobStruct->method('toArray')->willReturn([
                'source' => 'en-US',
                'target' => 'it-IT',
            ]);

            $response = [
                'successful' => true,
                'time' => 100,
            ];

            $sourceFileData = [
                'id_file' => 1,
                'filename' => 'test.docx',
                'mime_type' => 'docx',
                'sha1_original_file' => 'sha1',
            ];

            // Should not throw — caught internally
            $this->filters->logConversionToTarget($response, $tmpFile, $jobStruct, $sourceFileData);

            // Successful response → no backup even though insert failed
            self::assertEmpty($this->filters->backupCalls);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ── constants ──────────────────────────────────────────────────────

    #[Test]
    public function constantsHaveExpectedValues(): void
    {
        self::assertSame('/api/v2/original2xliff', Filters::SOURCE_TO_XLIFF_ENDPOINT);
        self::assertSame('/api/v2/xliff2translated', Filters::XLIFF_TO_TARGET_ENDPOINT);
    }
}
