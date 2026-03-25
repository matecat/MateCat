<?php

namespace unit\Model\ProjectCreation;

use Model\FilesStorage\AbstractFilesStorage;
use Model\ProjectCreation\FileInsertionService;
use Model\ProjectCreation\ProjectStructure;
use Throwable;

/**
 * Testable subclass of {@see FileInsertionService} that exposes protected
 * methods for unit testing and allows stubbing of internal collaborators.
 */
class TestableFileInsertionService extends FileInsertionService
{
    /**
     * When set, insertFiles() returns values from this queue instead of the real implementation.
     * @var list<array<int, array<string, mixed>>>
     */
    private array $insertFilesReturnQueue = [];

    /**
     * Records calls to insertFiles() for verification.
     * @var list<array{projectStructure: ProjectStructure, originalFileNames: list<string>, sha1: string, xliffPath: string}>
     */
    public array $insertFilesCalls = [];

    /**
     * When true, validateCachedXliff() is a no-op.
     */
    private bool $skipValidation = false;

    /**
     * Configure the return value(s) for the stubbed insertFiles().
     * When called with a single array, that value is returned for every call.
     * Use {@see enqueueInsertFilesReturn()} to configure per-call responses.
     */
    public function stubInsertFiles(array $return): void
    {
        $this->insertFilesReturnQueue = [$return];
    }

    /**
     * Enqueue multiple return values — one per insertFiles() call, in order.
     *
     * @param list<array<int, array<string, mixed>>> $returns
     */
    public function enqueueInsertFilesReturns(array $returns): void
    {
        $this->insertFilesReturnQueue = $returns;
    }

    /**
     * Skip validateCachedXliff() validation (no-op).
     */
    public function skipValidation(): void
    {
        $this->skipValidation = true;
    }

    /**
     * Public wrapper to invoke validateCachedXliff() (now protected).
     * @throws \Exception
     */
    public function callValidateCachedXliff(?string $cachedXliffFilePathName, array $_originalFileNames, array $linkFiles): void
    {
        $this->validateCachedXliff($cachedXliffFilePathName, $_originalFileNames, $linkFiles);
    }

    /**
     * Public wrapper to invoke mapFileInsertionError() (still private — use reflection).
     * @throws \ReflectionException
     */
    public function callMapFileInsertionError(ProjectStructure $projectStructure, Throwable $e): void
    {
        $ref = new \ReflectionClass(FileInsertionService::class);
        $method = $ref->getMethod('mapFileInsertionError');
        $method->invoke($this, $projectStructure, $e);
    }

    protected function validateCachedXliff(?string $cachedXliffFilePathName, array $_originalFileNames, array $linkFiles): void
    {
        if ($this->skipValidation) {
            return;
        }
        parent::validateCachedXliff($cachedXliffFilePathName, $_originalFileNames, $linkFiles);
    }

    protected function insertFiles(AbstractFilesStorage $fs, ProjectStructure $projectStructure, array $_originalFileNames, string $sha1_original, string $cachedXliffFilePathName): array
    {
        $this->insertFilesCalls[] = [
            'projectStructure' => $projectStructure,
            'originalFileNames' => $_originalFileNames,
            'sha1' => $sha1_original,
            'xliffPath' => $cachedXliffFilePathName,
        ];

        if (!empty($this->insertFilesReturnQueue)) {
            // If only one entry, reuse it for every call; otherwise shift from queue
            if (count($this->insertFilesReturnQueue) === 1) {
                return $this->insertFilesReturnQueue[0];
            }
            return array_shift($this->insertFilesReturnQueue);
        }

        return parent::insertFiles($fs, $projectStructure, $_originalFileNames, $sha1_original, $cachedXliffFilePathName);
    }
}
