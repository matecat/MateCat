<?php

namespace unit\Model\ProjectManager;

use ArrayObject;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::handleZipFiles()}.
 *
 * Uses TestableProjectManager's overridden _zipFileHandling() to control behavior.
 *
 * Verifies:
 * - Does nothing when _zipFileHandling succeeds
 * - Catches exception from _zipFileHandling, records project error, rethrows as EndQueueException
 * - Error code and message are preserved
 * - The linkFiles argument is forwarded to _zipFileHandling
 */
class HandleZipFilesTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );

        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
    }

    #[Test]
    public function doesNothingWhenZipHandlingSucceeds(): void
    {
        // Default callback is null, which means _zipFileHandling does nothing
        $this->pm->callHandleZipFiles(['zipHashes' => ['hash1']]);
        $this->assertCount(0, $this->pm->getTestProjectStructure()['result']['errors']);
    }

    #[Test]
    public function throwsEndQueueExceptionOnFailure(): void
    {
        $this->pm->setZipFileHandlingCallback(function () {
            throw new Exception('Zip storage failed', -10);
        });

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Zip storage failed');
        $this->expectExceptionCode(-10);

        $this->pm->callHandleZipFiles(['zipHashes' => ['hash1']]);
    }

    #[Test]
    public function recordsProjectErrorOnFailure(): void
    {
        $this->pm->setZipFileHandlingCallback(function () {
            throw new Exception('Storage error', -5);
        });

        try {
            $this->pm->callHandleZipFiles(['zipHashes' => []]);
        } catch (EndQueueException) {
            // expected
        }

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(1, $errors);
        $this->assertSame(-5, $errors[0]['code']);
        $this->assertSame('Storage error', $errors[0]['message']);
    }

    #[Test]
    public function forwardsLinkFilesToZipHandling(): void
    {
        $capturedLinkFiles = null;
        $this->pm->setZipFileHandlingCallback(function ($linkFiles) use (&$capturedLinkFiles) {
            $capturedLinkFiles = $linkFiles;
        });

        $input = ['zipHashes' => ['abc', 'def'], 'extra' => 'data'];
        $this->pm->callHandleZipFiles($input);

        $this->assertSame($input, $capturedLinkFiles);
    }

    #[Test]
    public function preservesExceptionCodeInEndQueueException(): void
    {
        $this->pm->setZipFileHandlingCallback(function () {
            throw new Exception('Custom error', -42);
        });

        try {
            $this->pm->callHandleZipFiles([]);
            $this->fail('Expected EndQueueException');
        } catch (EndQueueException $e) {
            $this->assertSame(-42, $e->getCode());
            $this->assertSame('Custom error', $e->getMessage());
            $this->assertInstanceOf(Exception::class, $e->getPrevious());
        }
    }

    #[Test]
    public function doesNotAddErrorWhenSuccessful(): void
    {
        $this->pm->setZipFileHandlingCallback(function () {
            // success - no exception
        });

        $this->pm->callHandleZipFiles(['zipHashes' => ['h1', 'h2', 'h3']]);

        $errors = $this->pm->getTestProjectStructure()['result']['errors'];
        $this->assertCount(0, $errors);
    }

    #[Test]
    public function wrapsOriginalExceptionAsPrevious(): void
    {
        $original = new Exception('Original cause', -99);

        $this->pm->setZipFileHandlingCallback(function () use ($original) {
            throw $original;
        });

        try {
            $this->pm->callHandleZipFiles([]);
            $this->fail('Expected EndQueueException');
        } catch (EndQueueException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
