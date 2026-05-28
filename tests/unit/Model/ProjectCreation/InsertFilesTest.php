<?php

namespace unit\Model\ProjectCreation;

use Exception;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\ProjectCreation\FileInsertionService;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockException;
use PHPUnit\Framework\MockObject\MockObject;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Minimal subclass to expose the protected {@see FileInsertionService::insertFiles()}
 * method for direct unit testing.
 */
class ExposedInsertFilesService extends FileInsertionService
{
    /**
     * @param list<string> $_originalFileNames
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function callInsertFiles(
        AbstractFilesStorage $fs,
        ProjectStructure $projectStructure,
        array $_originalFileNames,
        string $sha1_original,
        string $cachedXliffFilePathName,
    ): array {
        return $this->insertFiles($fs, $projectStructure, $_originalFileNames, $sha1_original, $cachedXliffFilePathName);
    }
}

/**
 * Unit tests for {@see FileInsertionService::insertFiles()}.
 *
 * Verifies:
 * - Invalid create_date throws Exception
 * - Blank filenames are skipped
 * - Happy path: inserts file record, moves from cache, returns correct structure
 * - moveFromCacheToFileDir returning non-true throws with FILE_MOVE_FAILED
 * - fid is appended to file_id_list
 * - Multiple files produce multiple structures keyed by fid
 * - pdfAnalysis metadata is inserted when present
 * - pdfAnalysis metadata is skipped when empty
 * - All blank filenames returns empty array
 * - Mixed blank/valid filenames only processes valid ones
 */
class InsertFilesTest extends AbstractTest
{
    private ProjectStructure $projectStructure;

    /**
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->projectStructure = new ProjectStructure([
            'id_project' => 42,
            'source_language' => 'en-US',
            'create_date' => '2025-03-19 10:00:00',
            'array_files_meta' => [],
            'file_id_list' => [],
            'result' => ['errors' => [], 'data' => []],
        ]);
    }

    /**
     * Build the service under test, accepting optional mock overrides.
     *
     * @throws MockException
     */
    private function buildService(
        ProjectManagerModel|null $pmModel = null,
        MetadataDao|null $metadataDao = null,
    ): ExposedInsertFilesService {
        return new ExposedInsertFilesService(
            $pmModel ?? $this->createStub(ProjectManagerModel::class),
            $metadataDao ?? $this->createStub(MetadataDao::class),
            null, // no GDrive session — avoids GoogleProvider::getClient() static call
            (function (string $fileName): void {})(...),
            $this->createStub(MatecatLogger::class),
        );
    }

    /**
     * Create a stub AbstractFilesStorage that returns the given fid from insertFile
     * and true from moveFromCacheToFileDir.
     *
     * @throws MockException
     */
    private function buildHappyPathStubs(string ...$fids): array
    {
        $pmModel = $this->createStub(ProjectManagerModel::class);
        $pmModel->method('insertFile')
            ->willReturnOnConsecutiveCalls(...$fids);

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('moveFromCacheToFileDir')
            ->willReturn(true);

        return [$pmModel, $fs];
    }

    // ── Test 1: Invalid create_date ─────────────────────────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function throwsExceptionWhenCreateDateIsInvalid(): void
    {
        $service = $this->buildService();
        $this->projectStructure->create_date = 'not-a-date';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid create_date for project');

        $service->callInsertFiles(
            $this->createStub(AbstractFilesStorage::class),
            $this->projectStructure,
            ['document.docx'],
            'abc123',
            '/cache/file.xliff',
        );
    }

    // ── Test 2: Skips empty filenames ────────────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function skipsEmptyFilenames(): void
    {
        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->never())->method('insertFile');

        /** @var AbstractFilesStorage&MockObject $fs */
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->never())->method('moveFromCacheToFileDir');

        $service = $this->buildService(pmModel: $pmModel);

        $result = $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            [''],
            'abc123',
            '/cache/file.xliff',
        );

        $this->assertSame([], $result);
    }

    // ── Test 3: Happy path ──────────────────────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function insertsFileRecordAndReturnsStructure(): void
    {
        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->once())
            ->method('insertFile')
            ->with(42, 'en-US', 'document.docx', 'docx', $this->stringContains('abc123'))
            ->willReturn('100');

        /** @var AbstractFilesStorage&MockObject $fs */
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('moveFromCacheToFileDir')
            ->willReturn(true);

        $service = $this->buildService(pmModel: $pmModel);

        $result = $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['document.docx'],
            'abc123',
            '/cache/file.xliff',
        );

        $this->assertArrayHasKey(100, $result);
        $this->assertSame(100, $result[100]['fid']);
        $this->assertSame('document.docx', $result[100]['original_filename']);
        $this->assertSame('/cache/file.xliff', $result[100]['path_cached_xliff']);
        $this->assertSame('docx', $result[100]['mime_type']);
    }

    // ── Test 4: File move failure ───────────────────────────────────

    /**
     * @throws MockException
     */
    #[Test]
    public function throwsExceptionWhenFileMovesFail(): void
    {
        $pmModel = $this->createStub(ProjectManagerModel::class);
        $pmModel->method('insertFile')->willReturn('100');

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('moveFromCacheToFileDir')->willReturn(false);

        $service = $this->buildService(pmModel: $pmModel);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(ProjectCreationError::FILE_MOVE_FAILED->value);

        $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['document.docx'],
            'abc123',
            '/cache/file.xliff',
        );
    }

    // ── Test 5: fid appended to file_id_list ────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function appendsFidToFileIdList(): void
    {
        [$pmModel, $fs] = $this->buildHappyPathStubs('100');
        $service = $this->buildService(pmModel: $pmModel);

        $this->assertSame([], $this->projectStructure->file_id_list);

        $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['document.docx'],
            'abc123',
            '/cache/file.xliff',
        );

        $this->assertSame([100], $this->projectStructure->file_id_list);
    }

    // ── Test 6: Multiple files ──────────────────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function insertsMultipleFilesAndReturnsAllStructures(): void
    {
        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->exactly(2))
            ->method('insertFile')
            ->willReturnOnConsecutiveCalls('100', '101');

        /** @var AbstractFilesStorage&MockObject $fs */
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->exactly(2))
            ->method('moveFromCacheToFileDir')
            ->willReturn(true);

        $service = $this->buildService(pmModel: $pmModel);

        $result = $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['file_a.docx', 'file_b.pdf'],
            'abc123',
            '/cache/file.xliff',
        );

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(100, $result);
        $this->assertArrayHasKey(101, $result);
        $this->assertSame('file_a.docx', $result[100]['original_filename']);
        $this->assertSame('file_b.pdf', $result[101]['original_filename']);
        $this->assertSame([100, 101], $this->projectStructure->file_id_list);
    }

    // ── Test 7: pdfAnalysis metadata inserted when present ──────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function insertsPdfAnalysisMetadataWhenPresent(): void
    {
        $pdfData = ['pages' => 3, 'hasImages' => true];

        $this->projectStructure->array_files_meta = [
            0 => ['mustBeConverted' => false, 'pdfAnalysis' => $pdfData],
        ];

        [$pmModel, $fs] = $this->buildHappyPathStubs('100');

        /** @var MetadataDao&MockObject $metadataDao */
        $metadataDao = $this->createMock(MetadataDao::class);
        $metadataDao->expects($this->once())
            ->method('insert')
            ->with(42, 100, 'pdfAnalysis', json_encode($pdfData));

        $service = $this->buildService(pmModel: $pmModel, metadataDao: $metadataDao);

        $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['document.pdf'],
            'abc123',
            '/cache/file.xliff',
        );
    }

    // ── Test 8: pdfAnalysis metadata skipped when empty ─────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function skipsPdfAnalysisMetadataWhenEmpty(): void
    {
        $this->projectStructure->array_files_meta = [
            0 => ['mustBeConverted' => false],
        ];

        [$pmModel, $fs] = $this->buildHappyPathStubs('100');

        /** @var MetadataDao&MockObject $metadataDao */
        $metadataDao = $this->createMock(MetadataDao::class);
        $metadataDao->expects($this->never())->method('insert');

        $service = $this->buildService(pmModel: $pmModel, metadataDao: $metadataDao);

        $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['document.docx'],
            'abc123',
            '/cache/file.xliff',
        );
    }

    // ── Test 9: All blank filenames returns empty array ──────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function returnsEmptyArrayWhenAllFilenamesEmpty(): void
    {
        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->never())->method('insertFile');

        $service = $this->buildService(pmModel: $pmModel);

        $result = $service->callInsertFiles(
            $this->createStub(AbstractFilesStorage::class),
            $this->projectStructure,
            ['', '', ''],
            'abc123',
            '/cache/file.xliff',
        );

        $this->assertSame([], $result);
        $this->assertSame([], $this->projectStructure->file_id_list);
    }

    // ── Test 10: Mixed blank/valid filenames ─────────────────────────

    /**
     * @throws MockException
     * @throws Exception
     */
    #[Test]
    public function processesOnlyNonEmptyFilenamesInMixedList(): void
    {
        /** @var ProjectManagerModel&MockObject $pmModel */
        $pmModel = $this->createMock(ProjectManagerModel::class);
        $pmModel->expects($this->exactly(2))
            ->method('insertFile')
            ->willReturnOnConsecutiveCalls('100', '101');

        /** @var AbstractFilesStorage&MockObject $fs */
        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->exactly(2))
            ->method('moveFromCacheToFileDir')
            ->willReturn(true);

        $service = $this->buildService(pmModel: $pmModel);

        $result = $service->callInsertFiles(
            $fs,
            $this->projectStructure,
            ['', 'file_a.docx', '', 'file_b.pdf', ''],
            'abc123',
            '/cache/file.xliff',
        );

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(100, $result);
        $this->assertArrayHasKey(101, $result);
        $this->assertSame([100, 101], $this->projectStructure->file_id_list);
    }
}
