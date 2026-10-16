<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectManagerModel;
use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\FileInsertionService::resolveAndInsertFiles()}.
 *
 * Verifies:
 * - Returns empty array when conversionHashes is not set
 * - Returns empty array when conversionHashes.sha is not set
 * - Skips malformed entries with no language suffix
 * - Resolves hash via getXliffFromCache and delegates to insertFiles
 * - Throws FILE_NOT_FOUND when insertFiles returns empty
 * - Exceptions from validateCachedXliff and insertFiles propagate unchanged
 *   (same class, code, message, no wrapping previous)
 * - Merges file structures from multiple hashes using += (preserves keys)
 * - Nothing is recorded on the project structure: createProject() owns the one error
 */
class ResolveAndInsertFilesTest extends AbstractTest
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
            (function (string $fileName): void {})(...),
            $this->createStub(MatecatLogger::class),
        );

        $this->projectStructure = new ProjectStructure([
            'source_language' => 'en-US',
            'result' => ['errors' => []],
        ]);
    }

    // ── Early returns for missing/empty conversionHashes ─────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function returnsEmptyArrayWhenConversionHashesNotSet(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $result = $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            [] // no conversionHashes key at all
        );

        $this->assertSame([], $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function returnsEmptyArrayWhenShaKeyNotSet(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $result = $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            ['conversionHashes' => []] // no 'sha' key
        );

        $this->assertSame([], $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function returnsEmptyArrayWhenShaArrayIsEmpty(): void
    {
        $fs = $this->createStub(AbstractFilesStorage::class);

        $result = $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            ['conversionHashes' => ['sha' => [], 'fileName' => []]]
        );

        $this->assertSame([], $result);
    }

    // ── Skips malformed entries ──────────────────────────────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function skipsMalformedEntriesWithNoLanguageSuffix(): void
    {
        // Hash without language delimiter — should be skipped
        $linkFiles = [
            'conversionHashes' => [
                'sha' => ['abc123'],
                'fileName' => ['abc123' => ['test.docx']],
            ],
        ];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->never())->method('getXliffFromCache');

        $result = $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            $linkFiles
        );

        $this->assertSame([], $result);
    }

    // ── Successful single-file resolution ────────────────────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function resolvesSingleHashAndReturnsFileStructure(): void
    {
        $sha1 = 'abc123def456';
        $hashKey = $sha1 . '__en-US';
        $cachedPath = '/cache/path/file.xliff';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKey],
                'fileName' => [$hashKey => ['document.docx']],
            ],
        ];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->once())
            ->method('getXliffFromCache')
            ->with($sha1, 'en-US')
            ->willReturn($cachedPath);

        $this->service->skipValidation();
        $this->service->stubInsertFiles([
            42 => [
                'fid' => 42,
                'original_filename' => 'document.docx',
                'path_cached_xliff' => $cachedPath,
                'mime_type' => 'docx',
            ],
        ]);

        $result = $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            $linkFiles
        );

        $this->assertArrayHasKey(42, $result);
        $this->assertSame('document.docx', $result[42]['original_filename']);
        $this->assertEmpty($this->projectStructure->result['errors']);

        // Verify insertFiles was called with correct arguments
        $this->assertCount(1, $this->service->insertFilesCalls);
        $call = $this->service->insertFilesCalls[0];
        $this->assertSame(['document.docx'], $call['originalFileNames']);
        $this->assertSame($sha1, $call['sha1']);
        $this->assertSame($cachedPath, $call['xliffPath']);
    }

    // ── Multiple hashes merge with += ────────────────────────────────

    /**
     * @throws Exception
     */
    #[Test]
    public function mergesMultipleHashResultsPreservingFidKeys(): void
    {
        $sha1A = 'aaaa1111';
        $sha1B = 'bbbb2222';
        $hashKeyA = $sha1A . '__en-US';
        $hashKeyB = $sha1B . '__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKeyA, $hashKeyB],
                'fileName' => [
                    $hashKeyA => ['file_a.docx'],
                    $hashKeyB => ['file_b.pdf'],
                ],
            ],
        ];

        $fs = $this->createMock(AbstractFilesStorage::class);
        $fs->expects($this->exactly(2))
            ->method('getXliffFromCache')
            ->willReturn('/cache/file.xliff');

        $this->service->skipValidation();
        $this->service->enqueueInsertFilesReturns([
            [
                10 => ['fid' => 10, 'original_filename' => 'file_a.docx', 'path_cached_xliff' => '/cache/file.xliff', 'mime_type' => 'docx'],
            ],
            [
                20 => ['fid' => 20, 'original_filename' => 'file_b.pdf', 'path_cached_xliff' => '/cache/file.xliff', 'mime_type' => 'pdf'],
            ],
        ]);

        $result = $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            $linkFiles
        );

        // += preserves numeric keys (fid 10 and fid 20)
        $this->assertCount(2, $result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertSame('file_a.docx', $result[10]['original_filename']);
        $this->assertSame('file_b.pdf', $result[20]['original_filename']);
    }

    // ── Empty insertFiles result ─────────────────────────────────────

    #[Test]
    public function throwsWhenInsertFilesReturnsEmpty(): void
    {
        $sha1 = 'abc123';
        $hashKey = $sha1 . '__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKey],
                'fileName' => [$hashKey => ['test.docx']],
            ],
        ];

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getXliffFromCache')->willReturn('/cache/file.xliff');

        $this->service->skipValidation();
        $this->service->stubInsertFiles([]); // empty — nothing inserted

        $this->expectException(Exception::class);
        $this->expectExceptionCode(ProjectCreationError::FILE_NOT_FOUND->value);

        $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            $linkFiles
        );
    }

    #[Test]
    public function emptyInsertFilesThrowsFileNotFoundAndRecordsNoError(): void
    {
        $sha1 = 'abc123';
        $hashKey = $sha1 . '__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKey],
                'fileName' => [$hashKey => ['test.docx']],
            ],
        ];

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getXliffFromCache')->willReturn('/cache/file.xliff');

        $this->service->skipValidation();
        $this->service->stubInsertFiles([]);

        try {
            $this->service->resolveAndInsertFiles(
                $fs,
                $this->projectStructure,
                $linkFiles
            );
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(Exception::class, $e::class);
            $this->assertSame(ProjectCreationError::FILE_NOT_FOUND->value, $e->getCode());
            $this->assertSame('Files could not be saved in database.', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }

        // createProject() records the one error, not the service
        $this->assertEmpty($this->projectStructure->result['errors']);
    }

    // ── Validation failures propagate unchanged ──────────────────────

    #[Test]
    public function nonXliffCachedFilePropagatesConversionNotFound(): void
    {
        $sha1 = 'abc123';
        $hashKey = $sha1 . '__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKey],
                'fileName' => [$hashKey => ['test.docx']],
            ],
        ];

        // An existing cached file without an XLIFF extension: validateCachedXliff throws XLIFF_CONVERSION_NOT_FOUND (-16)
        $notAnXliff = tempnam(sys_get_temp_dir(), 'resolve_insert_') . '.txt';
        touch($notAnXliff);

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getXliffFromCache')->willReturn($notAnXliff);

        try {
            $this->service->resolveAndInsertFiles(
                $fs,
                $this->projectStructure,
                $linkFiles
            );
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(Exception::class, $e::class);
            $this->assertSame(ProjectCreationError::XLIFF_CONVERSION_NOT_FOUND->value, $e->getCode());
            $this->assertSame('File not found. Failed to save XLIFF conversion on disk.', $e->getMessage());
            $this->assertNull($e->getPrevious());
        } finally {
            @unlink($notAnXliff);
            @unlink(substr($notAnXliff, 0, -4));
        }

        $this->assertSame([], $this->service->insertFilesCalls);
        $this->assertEmpty($this->projectStructure->result['errors']);
    }

    // ── getXliffFromCache returns false → null path ──────────────────

    #[Test]
    public function passesNullToCachedXliffWhenGetXliffFromCacheReturnsFalse(): void
    {
        $sha1 = 'abc123';
        $hashKey = $sha1 . '__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKey],
                'fileName' => [$hashKey => ['test.docx']],
            ],
        ];

        // getXliffFromCache returns false → should be converted to null
        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getXliffFromCache')->willReturn(false);

        // Don't skip validation — let it run and verify it gets null.
        // A null path is FILE_NOT_FOUND on both S3 and the local filesystem.
        $this->expectException(Exception::class);
        $this->expectExceptionCode(ProjectCreationError::FILE_NOT_FOUND->value);

        $this->service->resolveAndInsertFiles(
            $fs,
            $this->projectStructure,
            $linkFiles
        );
    }

    #[Test]
    public function emptyFileNamesPropagateTheValidationException(): void
    {
        $sha1 = 'abc123';
        $hashKey = $sha1 . '__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKey],
                'fileName' => [$hashKey => []], // empty file names → validation throws FILE_NOT_FOUND
            ],
        ];

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getXliffFromCache')->willReturn('/cache/file.xliff');

        try {
            $this->service->resolveAndInsertFiles(
                $fs,
                $this->projectStructure,
                $linkFiles
            );
            $this->fail('Expected exception');
        } catch (Exception $e) {
            $this->assertSame(Exception::class, $e::class);
            $this->assertSame(ProjectCreationError::FILE_NOT_FOUND->value, $e->getCode());
            $this->assertSame('No hash files found', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    // ── Stops at first error ────────────────────────────────────────

    #[Test]
    public function stopsProcessingAtFirstError(): void
    {
        $hashKeyA = 'aaaa__en-US';
        $hashKeyB = 'bbbb__en-US';

        $linkFiles = [
            'conversionHashes' => [
                'sha' => [$hashKeyA, $hashKeyB],
                'fileName' => [
                    $hashKeyA => ['first.docx'],
                    $hashKeyB => ['second.docx'],
                ],
            ],
        ];

        $fs = $this->createStub(AbstractFilesStorage::class);
        $fs->method('getXliffFromCache')->willReturn('/cache/file.xliff');

        $this->service->skipValidation();
        $this->service->stubInsertFiles([]); // empty → error on first iteration

        try {
            $this->service->resolveAndInsertFiles(
                $fs,
                $this->projectStructure,
                $linkFiles
            );
            $this->fail('Expected exception');
        } catch (Exception $e) {
            // fail() throws an Exception too: the code tells the expected failure apart
            $this->assertSame(ProjectCreationError::FILE_NOT_FOUND->value, $e->getCode());
        }

        // insertFiles should only be called once (stops at first error)
        $this->assertCount(1, $this->service->insertFilesCalls);
    }
}
