<?php

declare(strict_types=1);

namespace Matecat\Core\TMS;

use Controller\API\Commons\Exceptions\UnprocessableException;
use Exception;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use RuntimeException;
use Utils\Engines\EngineInterface;
use Utils\Engines\Lara;
use Utils\Engines\MMT;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\CreateUserResponse;
use Utils\Engines\Results\MyMemory\ExportResponse;
use Utils\Engines\Results\MyMemory\FileImportAndStatusResponse;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TMS\TMSFile;
use Utils\TMS\TMSService;

/**
 * A TMSService whose adaptive MT engines are given by the test, instead of being read from the
 * user's engine metadata.
 */
class AdaptiveEnginesTMSService extends TMSService
{
    /** @var EngineInterface[] */
    public array $adaptiveEngines = [];

    protected function getUserAdaptiveMTEngines(UserStruct $user): array
    {
        return $this->adaptiveEngines;
    }
}

class TMSServiceTest extends AbstractTest
{
    private TMSService $service;
    private MyMemory&Stub $myMemoryStub;
    private MatecatLogger&Stub $loggerStub;
    private IDatabase&Stub $dbStub;
    private PDO&Stub $pdoStub;
    private PDOStatement&Stub $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();

        $this->myMemoryStub = $this->createStub(MyMemory::class);
        $this->loggerStub = $this->createStub(MatecatLogger::class);

        $ref = new ReflectionClass(TMSService::class);
        $this->service = $ref->newInstanceWithoutConstructor();

        $engineProp = $ref->getProperty('mymemory_engine');
        $engineProp->setValue($this->service, $this->myMemoryStub);

        $loggerProp = $ref->getProperty('logger');
        $loggerProp->setValue($this->service, $this->loggerStub);

        $outputTypeProp = $ref->getProperty('output_type');
        $outputTypeProp->setValue($this->service, 'translation');

        $featureSetProp = $ref->getProperty('featureSet');
        $featureSetProp->setValue($this->service, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $nameProp = $ref->getProperty('name');
        $nameProp->setValue($this->service, '');

        $dbProp = $ref->getProperty('database');
        $dbProp->setValue($this->service, $this->dbStub);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        parent::tearDown();
    }

    // ── setOutputType / setName ─────────────────────────────────────────

    #[Test]
    public function setOutputTypeSetsValue(): void
    {
        $this->service->setOutputType('mt');

        $ref = new ReflectionClass($this->service);
        $prop = $ref->getProperty('output_type');
        self::assertSame('mt', $prop->getValue($this->service));
    }

    #[Test]
    public function setNameReturnsFluentInterface(): void
    {
        $result = $this->service->setName('my-tmx.tmx');
        self::assertSame($this->service, $result);

        $ref = new ReflectionClass($this->service);
        $prop = $ref->getProperty('name');
        self::assertSame('my-tmx.tmx', $prop->getValue($this->service));
    }

    // ── checkCorrectKey ─────────────────────────────────────────────────

    #[Test]
    public function checkCorrectKeyReturnsTrueForValidKey(): void
    {
        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);

        $result = $this->service->checkCorrectKey('valid-key');
        self::assertTrue($result);
    }

    #[Test]
    public function checkCorrectKeyPropagatesException(): void
    {
        $this->myMemoryStub->method('checkCorrectKey')
            ->willThrowException(new Exception('Invalid key'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid key');
        $this->service->checkCorrectKey('bad-key');
    }

    // ── createMyMemoryKey ───────────────────────────────────────────────

    #[Test]
    public function createMyMemoryKeyReturnsResponse(): void
    {
        $response = $this->createStub(CreateUserResponse::class);
        $this->myMemoryStub->method('createMyMemoryKey')->willReturn($response);

        $result = $this->service->createMyMemoryKey();
        self::assertSame($response, $result);
    }

    #[Test]
    public function createMyMemoryKeyWrapsExceptionWithCode(): void
    {
        $this->myMemoryStub->method('createMyMemoryKey')
            ->willThrowException(new Exception('API error'));

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-7);
        $this->service->createMyMemoryKey();
    }

    // ── addTmxInMyMemory ────────────────────────────────────────────────

    #[Test]
    public function addTmxInMyMemoryReturnsEmptyWarningsOnSuccess(): void
    {
        $file = new TMSFile('/tmp/test.tmx', 'tm-key-123', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['uuid' => 'abc-123', 'status' => 0],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        // Create the file so unlink doesn't warn
        touch('/tmp/test.tmx');

        $result = $this->service->addTmxInMyMemory($file, $user);
        self::assertSame([], $result);
        self::assertSame('abc-123', $file->getUuid());
    }

    #[Test]
    public function addTmxInMyMemoryThrowsOnStatus503(): void
    {
        $file = new TMSFile('/tmp/test503.tmx', 'tm-key', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 503,
            'responseData' => [],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        touch('/tmp/test503.tmx');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error uploading TMX file');
        $this->service->addTmxInMyMemory($file, $user);
    }

    #[Test]
    public function addTmxInMyMemoryThrowsOnStatus400(): void
    {
        $file = new TMSFile('/tmp/test400.tmx', 'tm-key', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 400,
            'responseData' => [],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        touch('/tmp/test400.tmx');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error uploading TMX file');
        $this->service->addTmxInMyMemory($file, $user);
    }

    #[Test]
    public function addTmxInMyMemoryThrowsOnStatus403WithFormattedMessage(): void
    {
        $file = new TMSFile('/tmp/test403.tmx', 'tm-key', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 403,
            'responseData' => [],
            'responseDetails' => 'THE CHARACTER SET PROVIDED IS INVALID.',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        touch('/tmp/test403.tmx');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('encoding of the TMX file');
        $this->service->addTmxInMyMemory($file, $user);
    }

    #[Test]
    public function addTmxInMyMemoryThrowsWhenImportIdIsNull(): void
    {
        $file = new TMSFile('/tmp/testNull.tmx', 'tm-key', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['uuid' => '', 'UUID' => ''],
            'responseDetails' => '',
        ]);
        $importResponse->id = null;

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        touch('/tmp/testNull.tmx');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return a valid import ID');
        $this->service->addTmxInMyMemory($file, $user);
    }

    // ── addGlossaryInMyMemory ───────────────────────────────────────────

    #[Test]
    public function addGlossaryInMyMemorySucceedsOnValidResponse(): void
    {
        $file = new TMSFile('/tmp/glossary.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['uuid' => 'gloss-uuid-1'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->service->addGlossaryInMyMemory($file);
        self::assertSame('gloss-uuid-1', $file->getUuid());
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsOnStatus400(): void
    {
        $file = new TMSFile('/tmp/glossary400.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 400,
            'responseData' => [],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Can't load glossary file right now");
        $this->service->addGlossaryInMyMemory($file);
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsOnStatus404(): void
    {
        $file = new TMSFile('/tmp/glossary404.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 404,
            'responseData' => [],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File format not supported');
        $this->service->addGlossaryInMyMemory($file);
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsOnStatus406WithStringDetails(): void
    {
        $file = new TMSFile('/tmp/glossary406.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 406,
            'responseData' => [],
            'responseDetails' => 'Some validation error',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Some validation error');
        $this->service->addGlossaryInMyMemory($file);
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsOnStatus406WithArrayDetails(): void
    {
        $file = new TMSFile('/tmp/glossary406b.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 406,
            'responseData' => [],
            'responseDetails' => ['error' => 'complex error'],
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('complex error');
        $this->service->addGlossaryInMyMemory($file);
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsOnStatus403HeaderMismatch(): void
    {
        $file = new TMSFile('/tmp/glossary403h.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 403,
            'responseData' => [],
            'responseDetails' => 'HEADER DON\'T MATCH THE CORRECT STRUCTURE',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(UnprocessableException::class);
        $this->expectExceptionMessage('file header does not match');
        $this->service->addGlossaryInMyMemory($file);
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsOnStatus403InvalidKey(): void
    {
        $file = new TMSFile('/tmp/glossary403k.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 403,
            'responseData' => [],
            'responseDetails' => 'INVALID KEY',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TM key provided');
        $this->service->addGlossaryInMyMemory($file);
    }

    #[Test]
    public function addGlossaryInMyMemoryThrowsWhenImportIdIsNull(): void
    {
        $file = new TMSFile('/tmp/glossaryNull.xlsx', 'tm-key', 'glossary.xlsx', 0);

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['uuid' => '', 'UUID' => ''],
            'responseDetails' => '',
        ]);
        $importResponse->id = null;

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('glossaryImport')->willReturn($importResponse);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return a valid glossary import ID');
        $this->service->addGlossaryInMyMemory($file);
    }

    // ── _fileUploadStatus ───────────────────────────────────────────────

    #[Test]
    public function fileUploadStatusReturnsCompletedOnStatus1(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['status' => '1', 'log' => '', 'uuid' => 'uuid-123'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $result = $this->service->_fileUploadStatus('uuid-123', 'TMX');
        self::assertTrue($result['completed']);
        self::assertSame(['status' => '1', 'log' => '', 'uuid' => 'uuid-123'], $result['data']);
    }

    #[Test]
    public function fileUploadStatusReturnsNotCompletedOnStatus0(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['status' => '0', 'log' => '', 'uuid' => 'uuid-123'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $result = $this->service->_fileUploadStatus('uuid-123', 'TMX');
        self::assertFalse($result['completed']);
    }

    #[Test]
    public function fileUploadStatusReturnsNotCompletedOnStatusMinus1(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['status' => '-1', 'log' => '', 'uuid' => 'uuid-123'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $result = $this->service->_fileUploadStatus('uuid-123', 'TMX');
        self::assertFalse($result['completed']);
    }

    #[Test]
    public function fileUploadStatusThrowsOnErrorResponse(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 500,
            'responseData' => ['status' => 2, 'log' => 'fatal error'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error response from TMX status check');
        $this->service->_fileUploadStatus('uuid-123', 'TMX');
    }

    #[Test]
    public function fileUploadStatusThrowsOnUnknownStatus(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['status' => '99', 'log' => '', 'uuid' => 'uuid-123'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid TMX');
        $this->service->_fileUploadStatus('uuid-123', 'TMX');
    }

    // ── glossaryUploadStatus / tmxUploadStatus ──────────────────────────

    #[Test]
    public function glossaryUploadStatusDelegatesToFileUploadStatus(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['status' => '1', 'log' => '', 'uuid' => 'uuid-456'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $result = $this->service->glossaryUploadStatus('uuid-456');
        self::assertTrue($result['completed']);
    }

    #[Test]
    public function tmxUploadStatusDelegatesToFileUploadStatus(): void
    {
        $statusResponse = new FileImportAndStatusResponse([
            'responseStatus' => 200,
            'responseData' => ['status' => '1', 'log' => '', 'uuid' => 'uuid-789'],
            'responseDetails' => '',
        ]);

        $this->myMemoryStub->method('getImportStatus')->willReturn($statusResponse);

        $result = $this->service->tmxUploadStatus('uuid-789');
        self::assertTrue($result['completed']);
    }

    // ── glossaryExport ──────────────────────────────────────────────────

    #[Test]
    public function glossaryExportDelegatesToMyMemory(): void
    {
        $response = $this->createStub(ExportResponse::class);
        $this->myMemoryStub->method('glossaryExport')->willReturn($response);

        $result = $this->service->glossaryExport('key-1', 'keyName', 'user@example.com', 'John');
        self::assertSame($response, $result);
    }

    // ── requestTMXEmailDownload ─────────────────────────────────────────

    #[Test]
    public function requestTMXEmailDownloadDelegatesToMyMemory(): void
    {
        $response = $this->createStub(ExportResponse::class);
        $this->myMemoryStub->method('emailExport')->willReturn($response);

        $this->service->setName('my-memory.tmx');
        $result = $this->service->requestTMXEmailDownload(
            'user@example.com',
            'John',
            'Doe',
            'tm-key-123',
            true
        );
        self::assertSame($response, $result);
    }

    // ── formatErrorMessage (private, tested through addTmxInMyMemory) ───

    #[Test]
    public function formatErrorMessageHandlesArrayInput(): void
    {
        $file = new TMSFile('/tmp/testArrayErr.tmx', 'tm-key', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 403,
            'responseData' => [],
            'responseDetails' => ['error' => 'structured error'],
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        touch('/tmp/testArrayErr.tmx');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error: {"error":"structured error"}');
        $this->service->addTmxInMyMemory($file, $user);
    }

    #[Test]
    public function formatErrorMessagePassesThroughGenericString(): void
    {
        $file = new TMSFile('/tmp/testGenErr.tmx', 'tm-key', 'test.tmx', 0);
        $user = new UserStruct();

        $importResponse = new FileImportAndStatusResponse([
            'responseStatus' => 403,
            'responseData' => [],
            'responseDetails' => 'SOME OTHER ERROR',
        ]);

        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn($importResponse);

        touch('/tmp/testGenErr.tmx');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error: SOME OTHER ERROR');
        $this->service->addTmxInMyMemory($file, $user);
    }

    // ── exportJobAsCSV ──────────────────────────────────────────────────

    #[Test]
    public function exportJobAsCSVReturnsValidCsvFile(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['segment' => 'Hello', 'translation' => 'Ciao'],
            ['segment' => 'World', 'translation' => 'Mondo'],
        ]);

        $result = $this->service->exportJobAsCSV(1, 'pass123', 'en-US', 'it-IT');

        $result->rewind();
        $header = $result->fgetcsv();
        self::assertSame(['Source: en-US', 'Target: it-IT'], $header);

        $row1 = $result->fgetcsv();
        self::assertSame(['Hello', 'Ciao'], $row1);

        $row2 = $result->fgetcsv();
        self::assertSame(['World', 'Mondo'], $row2);
    }

    #[Test]
    public function exportJobAsCSVReturnsEmptyFileWhenNoRows(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = $this->service->exportJobAsCSV(1, 'pass123', 'en-US', 'it-IT');

        $result->rewind();
        $header = $result->fgetcsv();
        self::assertSame(['Source: en-US', 'Target: it-IT'], $header);

        $emptyRow = $result->fgetcsv();
        self::assertFalse($emptyRow);
    }

    // ── exportJobAsTMX ──────────────────────────────────────────────────

    #[Test]
    public function exportJobAsTMXThrowsWhenJobNotFound(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Job not found');
        $this->service->exportJobAsTMX(999, 'bad-pass', 'en-US', 'it-IT');
    }

    #[Test]
    public function exportJobAsTMXReturnsValidTmxStructure(): void
    {
        $job = new JobStruct();
        $job->id = 1;
        $job->password = 'pass123';
        $job->job_first_segment = 1;
        $job->job_last_segment = 100;

        $translationRow = [
            'id_segment' => '10',
            'id_job' => '1',
            'filename' => 'test.xlf',
            'segment' => 'Hello world',
            'translation' => 'Ciao mondo',
            'translation_date' => '2025-01-01 12:00:00',
            'status' => 'TRANSLATED',
            'suggestions_array' => null,
            'tm_keys' => null,
        ];

        $fetchAllCount = 0;
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturnCallback(
            function () use ($job, $translationRow, &$fetchAllCount): array {
                $fetchAllCount++;
                return match ($fetchAllCount) {
                    1 => [$job],
                    3 => [$translationRow],
                    4 => [$job],
                    default => [],
                };
            }
        );
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = $this->service->exportJobAsTMX(1, 'pass123', 'en-US', 'it-IT');

        $result->rewind();
        $content = '';
        while (!$result->eof()) {
            $content .= $result->fgets();
        }

        self::assertStringContainsString('<?xml version="1.0"', $content);
        self::assertStringContainsString('<tmx version="1.4">', $content);
        self::assertStringContainsString('srclang="en-US"', $content);
        self::assertStringContainsString('Hello world', $content);
        self::assertStringContainsString('Ciao mondo', $content);
        self::assertStringContainsString('</tmx>', $content);
    }

    // ── addTmxInMyMemory: adaptive MT engines ───────────────────────────

    /**
     * A service whose adaptive engines are the given ones, built like the one of setUp().
     *
     * @param EngineInterface[] $engines
     */
    private function serviceWithAdaptiveEngines(array $engines): AdaptiveEnginesTMSService
    {
        $service = (new ReflectionClass(AdaptiveEnginesTMSService::class))->newInstanceWithoutConstructor();
        $parent  = new ReflectionClass(TMSService::class);

        foreach (['mymemory_engine', 'logger', 'output_type', 'featureSet', 'name', 'database'] as $prop) {
            $parent->getProperty($prop)->setValue($service, $parent->getProperty($prop)->getValue($this->service));
        }

        $service->adaptiveEngines = $engines;

        return $service;
    }

    /**
     * Imports a TMX on $tmKey with the given adaptive engines, and returns the mt_import flag
     * MyMemory received.
     *
     * @param EngineInterface[] $engines
     *
     * @throws Exception
     */
    private function mtImportSentToMyMemory(string $tmKey, array $engines): ?bool
    {
        $myMemory = $this->createMock(MyMemory::class);
        $myMemory->method('checkCorrectKey')->willReturn(true);

        $sent = null;
        $myMemory->expects($this->once())
            ->method('importMemory')
            ->willReturnCallback(function (string $filePath, string $key, UserStruct $user, bool $mtImport) use (&$sent): FileImportAndStatusResponse {
                $sent = $mtImport;

                return new FileImportAndStatusResponse([
                    'responseStatus'  => 200,
                    'responseData'    => ['uuid' => 'abc-123', 'status' => 0],
                    'responseDetails' => '',
                ]);
            });

        $service = $this->serviceWithAdaptiveEngines($engines);
        (new ReflectionClass(TMSService::class))->getProperty('mymemory_engine')->setValue($service, $myMemory);

        $defaultKey = AppConfig::$DEFAULT_TM_KEY;
        AppConfig::$DEFAULT_TM_KEY = 'default-mm-key';

        try {
            touch('/tmp/test.tmx');
            $service->addTmxInMyMemory(new TMSFile('/tmp/test.tmx', $tmKey, 'test.tmx', 0), new UserStruct());
        } finally {
            AppConfig::$DEFAULT_TM_KEY = $defaultKey;
        }

        return $sent;
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsMtImportFalseForTheDefaultKey(): void
    {
        self::assertFalse($this->mtImportSentToMyMemory('default-mm-key', []));
    }

    /**
     * The default key wins over the engines: a user with Lara still sends false and imports on no
     * engine.
     *
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsMtImportFalseForTheDefaultKeyEvenWithAnAdaptiveEngine(): void
    {
        $lara = $this->createMock(Lara::class);
        $lara->expects($this->never())->method('importMemory');

        self::assertFalse($this->mtImportSentToMyMemory('default-mm-key', [$lara]));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsMtImportFalseForAUserWithLara(): void
    {
        self::assertFalse($this->mtImportSentToMyMemory('tm-key-123', [$this->createStub(Lara::class)]));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsMtImportFalseForAUserWithModernMtOnly(): void
    {
        self::assertFalse($this->mtImportSentToMyMemory('tm-key-123', [$this->createStub(MMT::class)]));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsMtImportFalseForAUserWithLaraAndModernMt(): void
    {
        self::assertFalse($this->mtImportSentToMyMemory('tm-key-123', [$this->createStub(Lara::class), $this->createStub(MMT::class)]));
    }

    /**
     * No adaptive engine in Matecat: MyMemory imports into Lara with its own license.
     *
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsMtImportTrueForAUserWithoutAdaptiveEngines(): void
    {
        self::assertTrue($this->mtImportSentToMyMemory('tm-key-123', []));
    }

    /**
     * The engines are read before the MyMemory import, so the flag can be computed: a user with
     * Lara is asked for its engines once, before importMemory, and imports on Lara after it.
     *
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemoryReadsTheAdaptiveEnginesBeforeTheMyMemoryImport(): void
    {
        $calls = [];

        $myMemory = $this->createStub(MyMemory::class);
        $myMemory->method('checkCorrectKey')->willReturn(true);
        $myMemory->method('importMemory')->willReturnCallback(function () use (&$calls): FileImportAndStatusResponse {
            $calls[] = 'mymemory';

            return new FileImportAndStatusResponse(['responseStatus' => 200, 'responseData' => ['uuid' => 'abc-123', 'status' => 0], 'responseDetails' => '']);
        });

        $lara = $this->createStub(Lara::class);
        $lara->method('importMemory')->willReturnCallback(function () use (&$calls): void {
            $calls[] = 'lara';
        });

        $service = $this->getMockBuilder(AdaptiveEnginesTMSService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserAdaptiveMTEngines'])
            ->getMock();
        $service->expects($this->once())->method('getUserAdaptiveMTEngines')->willReturnCallback(function () use (&$calls, $lara): array {
            $calls[] = 'engines';

            return [$lara];
        });

        $parent = new ReflectionClass(TMSService::class);
        foreach (['logger', 'output_type', 'featureSet', 'name', 'database'] as $prop) {
            $parent->getProperty($prop)->setValue($service, $parent->getProperty($prop)->getValue($this->service));
        }
        $parent->getProperty('mymemory_engine')->setValue($service, $myMemory);

        touch('/tmp/test.tmx');
        $service->addTmxInMyMemory(new TMSFile('/tmp/test.tmx', 'tm-key-123', 'test.tmx', 0), new UserStruct());

        self::assertSame(['engines', 'mymemory', 'lara'], $calls);
    }

    private function stubSuccessfulMyMemoryImport(): void
    {
        $this->myMemoryStub->method('checkCorrectKey')->willReturn(true);
        $this->myMemoryStub->method('importMemory')->willReturn(new FileImportAndStatusResponse([
            'responseStatus'  => 200,
            'responseData'    => ['uuid' => 'abc-123', 'status' => 0],
            'responseDetails' => '',
        ]));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemorySendsAPrivateKeyTmxToTheAdaptiveEngines(): void
    {
        $defaultKey = AppConfig::$DEFAULT_TM_KEY;
        AppConfig::$DEFAULT_TM_KEY = 'default-mm-key';

        try {
            $this->stubSuccessfulMyMemoryImport();
            $user = new UserStruct();

            $engine = $this->createMock(EngineInterface::class);
            $engine->expects($this->once())
                ->method('importMemory')
                ->with('/tmp/test.tmx', 'tm-key-123', $user);

            touch('/tmp/test.tmx');
            $file = new TMSFile('/tmp/test.tmx', 'tm-key-123', 'test.tmx', 0);

            self::assertSame([], $this->serviceWithAdaptiveEngines([$engine])->addTmxInMyMemory($file, $user));
            self::assertSame('abc-123', $file->getUuid());
        } finally {
            AppConfig::$DEFAULT_TM_KEY = $defaultKey;
        }
    }

    /**
     * The default key is the TM shared by everyone: its TMX goes to MyMemory only, never to an
     * adaptive engine such as Lara.
     *
     * @throws Exception
     */
    #[Test]
    public function addTmxInMyMemoryNeverSendsADefaultKeyTmxToTheAdaptiveEngines(): void
    {
        $defaultKey = AppConfig::$DEFAULT_TM_KEY;
        AppConfig::$DEFAULT_TM_KEY = 'default-mm-key';

        try {
            $this->stubSuccessfulMyMemoryImport();

            $engine = $this->createMock(EngineInterface::class);
            $engine->expects($this->never())->method('importMemory');

            touch('/tmp/test.tmx');
            $file = new TMSFile('/tmp/test.tmx', 'default-mm-key', 'test.tmx', 0);

            self::assertSame([], $this->serviceWithAdaptiveEngines([$engine])->addTmxInMyMemory($file, new UserStruct()));
            self::assertSame('abc-123', $file->getUuid());
        } finally {
            AppConfig::$DEFAULT_TM_KEY = $defaultKey;
        }
    }
}
