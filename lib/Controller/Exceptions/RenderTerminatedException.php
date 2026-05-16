<?php

namespace Controller\Exceptions;

use RuntimeException;

/**
 * Thrown in place of die() during test runs so that view controller
 * render() calls do not terminate the PHPUnit process.
 *
 * @see \Controller\Abstracts\BaseKleinViewController::render()
 */
class RenderTerminatedException extends RuntimeException
{
}
