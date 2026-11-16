<?php

namespace Model\ProjectCreation;

use Exception;
use Throwable;

/**
 * A project creation failure whose message is written for the user.
 *
 * createProject() shows the code and message of this exception only. Any other throwable is an
 * internal failure: it is logged and reported as CREATION_FAILED. An integer code cannot tell the
 * two apart, because other subsystems throw with the same numbers (FilesStorage -14, HTTP 400).
 */
final class ProjectCreationException extends Exception
{
    public function __construct(ProjectCreationError $error, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $error->value, $previous);
    }
}
