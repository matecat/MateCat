<?php

use Klein\Klein;
use PHPUnit\Framework\MockObject\MockObject;
use Utils\ActiveMQ\AMQHandler;
use Utils\ActiveMQ\WorkerClient;

/**
 * Test-only holder for the connection (or test double) that obtainTestDatabase() hands back.
 *
 * Replaces the old reflection-into-Database::$instance trick: AbstractTest::createDatabaseMock()
 * and setDatabaseInstance() call set() to install a stub; resetDatabaseMock() calls reset().
 * When no override is set, get() returns the composition-root connection (Bootstrap::getDatabase()).
 */
class TestDatabaseProvider
{
    private static ?\Model\DataAccess\IDatabase $override = null;

    public static function set(?\Model\DataAccess\IDatabase $db): void
    {
        self::$override = $db;
    }

    public static function reset(): void
    {
        self::$override = null;
    }

    public static function get(): \Model\DataAccess\IDatabase
    {
        return self::$override ?? \Bootstrap::getDatabase();
    }
}

/**
 * Test-only replacement for the removed Database::obtain() singleton accessor.
 *
 * Production code builds the DB connection at the composition root (Bootstrap::start())
 * and injects it. Tests boot Bootstrap via test_helper.php, so by default this returns the
 * composition-root connection — unless a test installed an override via TestDatabaseProvider.
 * The optional credential args mirror the old obtain() signature for the rare tests that need
 * a connection to a specific database (those always get a fresh real connection).
 */
function obtainTestDatabase(?string $server = null, ?string $user = null, ?string $password = null, ?string $database = null): \Model\DataAccess\IDatabase
{
    if ($server !== null && $user !== null && $password !== null && $database !== null) {
        return new \Model\DataAccess\Database($server, $user, $password, $database);
    }

    return \TestDatabaseProvider::get();
}

function sig_handler($signo)
{
    echo "\n\033[41m" . str_pad("Caught signal \033[1m$signo", 39, " ", STR_PAD_BOTH) . "\033[0m\n";
    switch ($signo) {
        case SIGHUP:
        case SIGTERM:
        case SIGINT:
            // handle shutdown tasks
            exit;
        default:
            // handle all other signals
    }
}

function setupSignalHandler()
{
    pcntl_signal(SIGTERM, "sig_handler");
    pcntl_signal(SIGHUP, "sig_handler");
    pcntl_signal(SIGINT, "sig_handler");
    echo "\033[0;30;42m" . str_pad("Signal handler installed.", 35, " ", STR_PAD_BOTH) . "\033[0m\n";
}

/**
 * We are not inside a TestUnit, we can't simply invoke
 *
 * <code>
 *     $this->getMockBuilder('\AMQHandler')->getMock()
 * </code>
 *
 * We have to manually instantiate a MockObject Generator
 *
 * @return void
 */
function disableAmqWorkerClientHelper(): void
{
    WorkerClient::$_HANDLER = (new PHPUnit\Framework\MockObject\Generator\Generator())->testDouble(
        AMQHandler::class,
        true
    );
}

function route()
{
    // fake function for router command in Matecat
}

/**
 * We are not inside a TestUnit, we can't simply invoke
 *
 * <code>
 *     $this->getMockBuilder(Klein::class)->getMock()
 * </code>
 *
 * We have to manually instantiate a MockObject Generator
 *
 * @return MockObject
 */
function mockKleinFramework(): MockObject
{
    return (new PHPUnit\Framework\MockObject\Generator\Generator())->testDouble(
        Klein::class,
        true
    );
}

/**
 * @throws Exception
 */
function getResourcePath(string $relativePath, string $pluginName = null): string
{
    if (file_exists(realpath(TEST_DIR . '/resources/' . $relativePath))) {
        return realpath(TEST_DIR . '/resources/' . $relativePath);
    } elseif (file_exists(realpath(TEST_DIR . "/../plugins/$pluginName/tests/resources/" . $relativePath))) {
        return realpath(TEST_DIR . "/../plugins/$pluginName/tests/resources/" . $relativePath);
    }
    throw new Exception("Resource not found: $relativePath " . ($pluginName ? "in plugin $pluginName" : ""));
}
