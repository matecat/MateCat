<?php

namespace Utils\TaskRunner\Exceptions;

use RuntimeException;

/**
 * Thrown in place of die()/exit() inside a daemon during test runs so that a
 * daemon's terminal failure path does not terminate the PHPUnit process.
 *
 * In production the daemon still calls die(); only the 'testing' environment
 * throws this instead (see FastAnalysis::__construct). It extends RuntimeException
 * and is a subtype of Exception, so methods already declaring `@throws Exception`
 * cover it — no PHPStan unchecked-exception config or baseline entry is required.
 */
class DaemonTerminatedException extends RuntimeException
{
}
