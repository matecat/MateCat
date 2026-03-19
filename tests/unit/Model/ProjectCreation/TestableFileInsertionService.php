<?php

namespace unit\Model\ProjectCreation;

use Model\ProjectCreation\FileInsertionService;
use Model\ProjectCreation\ProjectStructure;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Testable subclass of {@see FileInsertionService} that exposes private
 * methods for unit testing.
 */
class TestableFileInsertionService extends FileInsertionService
{
    /**
     * Public wrapper to invoke the private validateCachedXliff().
     * @throws ReflectionException
     * @throws \Exception
     */
    public function callValidateCachedXliff(?string $cachedXliffFilePathName, array $_originalFileNames, array $linkFiles): void
    {
        $ref = new ReflectionClass(FileInsertionService::class);
        $method = $ref->getMethod('validateCachedXliff');
        $method->invoke($this, $cachedXliffFilePathName, $_originalFileNames, $linkFiles);
    }

    /**
     * Public wrapper to invoke the private mapFileInsertionError().
     * @throws ReflectionException
     */
    public function callMapFileInsertionError(ProjectStructure $projectStructure, Throwable $e): void
    {
        $ref = new ReflectionClass(FileInsertionService::class);
        $method = $ref->getMethod('mapFileInsertionError');
        $method->invoke($this, $projectStructure, $e);
    }
}
