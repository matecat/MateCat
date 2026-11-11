<?php

namespace Model\Concerns;

use Throwable;
use Utils\Logger\MatecatLogger;
use View\API\Commons\Error;

/**
 * Shared logging helper for ProjectManager composition classes.
 *
 * Every class that uses this trait inherits a `$logger` property
 * and a `log()` method that delegates to MatecatLogger::debug(),
 * rendering exceptions via Error::render() when present.
 *
 * The consuming class must initialise `$this->logger` in its
 * constructor (e.g. via dependency injection).
 */
trait LogsMessages
{
    private MatecatLogger $logger;

    private function log(mixed $_msg, ?Throwable $exception = null): void
    {
        if (!$exception) {
            $this->logger->debug($_msg);
        } else {
            $this->logger->debug($_msg, (new Error($exception))->render(true));
        }
    }
}
