<?php

namespace Model\ProjectCreation;

use Exception;
use Throwable;

/**
 * Thrown by {@see FileInsertionService} when file insertion fails.
 *
 * The caller ({@see ProjectManager}) catches this to handle project-level
 * cleanup ({@see ProjectManager::clearFailedProject()}) and queue management.
 */
class FileInsertionException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
