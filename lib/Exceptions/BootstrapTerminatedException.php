<?php

namespace Exceptions;

use RuntimeException;

/**
 * Thrown in place of die() during test runs so that Bootstrap's
 * exceptionHandler() and shutdownFunctionHandler() do not terminate
 * the PHPUnit process.
 *
 * @see \Bootstrap::exceptionHandler()
 * @see \Bootstrap::shutdownFunctionHandler()
 */
class BootstrapTerminatedException extends RuntimeException
{
    public function __construct(
        public readonly int $httpStatusCode = 0,
    ) {
        parent::__construct('Bootstrap terminated', $httpStatusCode);
    }
}
