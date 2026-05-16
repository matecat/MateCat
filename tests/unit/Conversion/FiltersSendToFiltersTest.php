<?php

namespace unit\Conversion;

use Exception;
use Model\Conversion\Filters;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\Network\MultiCurlHandler;
use Utils\Registry\AppConfig;

class FiltersWithMockedCurl extends Filters
{
    public ?MultiCurlHandler $mockCurl = null;

    public ?PDO $mockPdo = null;

    /** @var array<int, string> */
    public array $backupCalls = [];

    protected function createMultiCurlHandler(): MultiCurlHandler
    {
        return $this->mockCurl ?? new MultiCurlHandler();
    }

    protected function createLogConnection(): PDO
    {
        if ($this->mockPdo !== null) {
            return $this->mockPdo;
        }

        throw new Exception('No mock PDO');
    }

    protected function backupFailedConversion(string &$sentFile): void
    {
        $this->backupCalls[] = $sentFile;
    }
}

class FiltersSendToFiltersTest extends TestCase
{
    private FiltersWithMockedCurl $filters;

    private ?string $originalStorageDir = null;
    private ?string $originalFiltersAddress = null;
    private ?string $originalFiltersUserAgent = null;
    private ?string $originalFiltersRapidApiKey = null;
    private string|false $originalFiltersForceVersion = false;
    private bool $originalFiltersEmailFailures = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStorageDir = AppConfig::$STORAGE_DIR;
        $this->originalFiltersAddress = AppConfig::$FILTERS_ADDRESS;
        $this->originalFiltersUserAgent = AppConfig::$FILTERS_USER_AGENT;
        $this->originalFiltersRapidApiKey = AppConfig::$FILTERS_RAPIDAPI_KEY;
        $this->originalFiltersForceVersion = AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION;
        $this->originalFiltersEmailFailures = AppConfig::$FILTERS_EMAIL_FAILURES;

        $this->filters = new FiltersWithMockedCurl();
        AppConfig::$FILTERS_ADDRESS = 'http://localhost:8080';
        AppConfig::$FILTERS_USER_AGENT = 'Matecat-Test';
        AppConfig::$FILTERS_RAPIDAPI_KEY = '';
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = '';
        AppConfig::$FILTERS_EMAIL_FAILURES = false;
        AppConfig::$STORAGE_DIR = sys_get_temp_dir();
    }

    protected function tearDown(): void
    {
        AppConfig::$STORAGE_DIR = $this->originalStorageDir;
        AppConfig::$FILTERS_ADDRESS = $this->originalFiltersAddress;
        AppConfig::$FILTERS_USER_AGENT = $this->originalFiltersUserAgent;
        AppConfig::$FILTERS_RAPIDAPI_KEY = $this->originalFiltersRapidApiKey;
        AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION = $this->originalFiltersForceVersion;
        AppConfig::$FILTERS_EMAIL_FAILURES = $this->originalFiltersEmailFailures;

        parent::tearDown();
    }

    private function createMockedCurl(string $responseBody, int $httpCode = 200, string $headerLine = ''): MultiCurlHandler
    {
        $curl = $this->createStub(MultiCurlHandler::class);
        $curl->method('createResource')->willReturn('0');
        $curl->method('getAllContents')->willReturn(['0' => $responseBody]);
        $curl->method('getAllInfo')->willReturn(['0' => [
            'http_code' => $httpCode,
            'errno' => 0,
            'error' => '',
            'curlinfo_total_time' => 0.123,
        ]]);

        $headers = $headerLine !== ''
            ? ['0' => [$headerLine]]
            : ['0' => []];
        $curl->method('getAllHeaders')->willReturn($headers);

        return $curl;
    }

    // ── successful response processing ─────────────────────────────────

    #[Test]
    public function sendToFiltersProcessesSuccessfulJsonResponse(): void
    {
        $this->filters->mockCurl = $this->createMockedCurl(
            json_encode(['key' => 'value']),
            200
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertIsArray($result);
            self::assertSame('value', $result['key']);
            self::assertArrayHasKey('time', $result);
            self::assertSame(123.0, $result['time']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sendToFiltersDecodesDocumentField(): void
    {
        $docContent = 'xliff binary content';
        $this->filters->mockCurl = $this->createMockedCurl(
            json_encode(['document' => base64_encode($docContent), 'other' => 'data']),
            200
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertArrayNotHasKey('document', $result);
            self::assertArrayHasKey('document_content', $result);
            self::assertSame($docContent, $result['document_content']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sendToFiltersExtractsInstanceHeaders(): void
    {
        $this->filters->mockCurl = $this->createMockedCurl(
            json_encode(['ok' => true]),
            200,
            'Filters-Instance: address=10.0.0.1:8080; version=2.5.0'
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertSame('10.0.0.1:8080', $result['instanceAddress']);
            self::assertSame('2.5.0', $result['instanceVersion']);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ── error response processing ──────────────────────────────────────

    #[Test]
    public function sendToFiltersHandlesHttpError(): void
    {
        $this->filters->mockCurl = $this->createMockedCurl(
            json_encode(['errorMessage' => 'net.translated.matecat.filters.ExtendedExcelException: Bad format']),
            500
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertFalse($result['isSuccess']);
            self::assertSame('Bad format', $result['errorMessage']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sendToFiltersHandlesRapidApiAuthError(): void
    {
        $this->filters->mockCurl = $this->createMockedCurl(
            '{"message":"Invalid RapidAPI Key"}',
            401
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertFalse($result['isSuccess']);
            self::assertStringContainsString('FILTERS_RAPIDAPI_KEY', $result['errorMessage']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sendToFiltersHandlesCurlError(): void
    {
        $curl = $this->createStub(MultiCurlHandler::class);
        $curl->method('createResource')->willReturn('0');
        $curl->method('getAllContents')->willReturn(['0' => false]);
        $curl->method('getAllInfo')->willReturn(['0' => [
            'http_code' => 0,
            'errno' => 28,
            'error' => 'Connection timed out',
            'curlinfo_total_time' => 30.0,
        ]]);
        $curl->method('getAllHeaders')->willReturn(['0' => []]);

        $this->filters->mockCurl = $curl;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertFalse($result['isSuccess']);
            self::assertStringContainsString('Curl error 28', $result['errorMessage']);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sendToFiltersHandlesStatusCodeOnlyError(): void
    {
        $this->filters->mockCurl = $this->createMockedCurl(
            json_encode(['no_error_field' => true]),
            503
        );

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertFalse($result['isSuccess']);
            self::assertStringContainsString('503', $result['errorMessage']);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ── headers[$id] is true (non-array) ───────────────────────────────

    #[Test]
    public function sendToFiltersHandlesTrueHeaderValue(): void
    {
        $curl = $this->createStub(MultiCurlHandler::class);
        $curl->method('createResource')->willReturn('0');
        $curl->method('getAllContents')->willReturn(['0' => json_encode(['ok' => true])]);
        $curl->method('getAllInfo')->willReturn(['0' => [
            'http_code' => 200,
            'errno' => 0,
            'error' => '',
            'curlinfo_total_time' => 0.05,
        ]]);
        $curl->method('getAllHeaders')->willReturn(['0' => true]);

        $this->filters->mockCurl = $curl;

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');

            self::assertTrue($result['ok']);
            self::assertArrayNotHasKey('instanceAddress', $result);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function sendToFiltersSkipsRapidApiHeaderWhenKeyEmpty(): void
    {
        AppConfig::$FILTERS_RAPIDAPI_KEY = '';

        $this->filters->mockCurl = $this->createMockedCurl(json_encode(['ok' => true]), 200);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test-src-');
        file_put_contents($tmpFile, 'content');

        try {
            $result = $this->filters->sourceToXliff($tmpFile, 'en-US', 'it-IT');
            self::assertIsArray($result);
        } finally {
            @unlink($tmpFile);
        }
    }
}
