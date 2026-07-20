<?php

namespace Controller\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \Controller\Abstracts\KleinController::getDatabase()} when a
 * controller is used without a database: neither a Klein App exposing a
 * `getDatabase` service nor a pre-injected `$database` was provided.
 *
 * This signals a wiring/programmer error (web dispatch always supplies the App
 * via router.php), so it is registered as an unchecked exception in phpstan.neon.
 */
class MissingDatabaseException extends RuntimeException
{
}
