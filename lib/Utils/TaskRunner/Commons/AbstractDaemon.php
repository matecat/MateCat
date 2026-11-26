<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 20.55
 *
 */

namespace Utils\TaskRunner\Commons;

use Exception;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * The abstract Daemon definition.
 * Extended by every concrete running daemon class
 *
 * - Singleton pattern for the concrete daemon
 * - Install signal handlers for the concrete class
 * - Provide log method for the concrete classes
 *
 * @phpstan-consistent-constructor
 */
abstract class AbstractDaemon
{

    use SignalHandlerTrait;

    /**
     * @var AMQHandler|null
     */
    protected ?AMQHandler $queueHandler;

    /**
     * Flag for control the instance running status. Setting to false cause the daemon to stop.
     *
     * @var bool
     */
    public bool $RUNNING = true;

    /**
     * The process id of the daemon
     *
     * @var int
     */
    public int $myProcessPid;

    /**
     * Sleep time in seconds. To be used as a parameter of sleep()
     * @var int
     */
    protected static int $sleepTime = 2;

    /**
     * @var string
     */
    protected string $_configFile;

    /**
     * Optional context index on which the task runner works
     *
     * @var string|null
     */
    protected ?string $_contextIndex;

    /**
     * Context list definitions
     *
     * @var ContextList
     */
    protected ContextList $_queueContextList;

    /**
     * @var MatecatLogger
     */
    protected MatecatLogger $logger;

    /**
     * MD5 of the config file version last read; '' when that version could not be read; null before the first read.
     *
     * Example: the file is untouched since the last read, so the next check reads nothing.
     */
    private ?string $configHash = null;

    /**
     * MD5 of a changed config file seen at the previous check and not read yet; null when there is none.
     *
     * Example: a save seen at one check is read at the next one, if its hash is still this one.
     */
    private ?string $pendingConfigHash = null;

    /**
     * AbstractDaemon constructor.
     *
     * @param ?string $configFile
     * @param ?string $contextIndex
     *
     * @throws Exception
     */
    protected function __construct(string $configFile = null, ?string $contextIndex = null)
    {
        AppConfig::$PRINT_ERRORS = true;
        $this->myProcessPid     = posix_getpid();
        $this->_configFile      = $configFile ?? '';
        $this->_contextIndex    = $contextIndex;
    }

    /**
     * Singleton Pattern, Unique Instance of This  ( Concrete class )
     *
     * @param ?string $config_file
     * @param string|null $queueIndex
     *
     * @return static
     * @throws Exception
     */
    public static function getInstance(?string $config_file = null, ?string $queueIndex = null): AbstractDaemon
    {
        $__INSTANCE = new static($config_file, $queueIndex);
        $__INSTANCE->installHandler();

        return $__INSTANCE;
    }

    /**
     * The starting method
     *
     * @param array<mixed>|null $args
     *
     * @return void
     */
    abstract public function main(array $args = null): void;

    /**
     * Needed to be abstract and static despite the strict standards because it will be called from signal handler
     * @return void
     */
    abstract public function cleanShutDown(): void;

    /**
     * Every cycle reloads and updates Daemon configuration.
     * @return void
     * @throws Exception
     */
    abstract protected function _updateConfiguration(): void;

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function getConfiguration(): Configuration
    {
        return new Configuration($this->_configFile, $this->_contextIndex);
    }

    /**
     * Reads the config file when it changed and the change held still for one check; null when there is nothing new.
     *
     * Each daemon calls it at every cycle, so a new cap or queue applies with no restart. Waiting one check skips a
     * file caught while it is being written: its hash changes again before the next check.
     *
     * Example: a save that adds P5 is returned at the second check after it, with a `config_reloaded` line.
     *
     * @throws Exception
     * @throws TypeError
     */
    protected function _readChangedConfiguration(): ?Configuration
    {
        // md5_file() gives false on an unreadable file: '' then counts as one more version, skipped once
        $hash = (string)@md5_file($this->_configFile);
        if ($hash === $this->configHash) {
            $this->pendingConfigHash = null;

            return null;
        }

        $firstRead = $this->configHash === null;
        if (!$firstRead && $hash !== $this->pendingConfigHash) {
            $this->pendingConfigHash = $hash;

            return null;
        }

        // Kept even when this version cannot be read, so its error is logged once and not on every cycle
        $this->configHash = $hash;
        $this->pendingConfigHash = null;

        // Start-up: not caught on purpose, a bad file must stop the daemon
        if ($firstRead) {
            return $this->_readValidConfiguration();
        }

        try {
            $configuration = $this->_readValidConfiguration();
        } catch (Exception|TypeError $e) {
            // A running daemon keeps working with the settings it has
            $this->logger->error('config_reload_failed', ['file' => $this->_configFile, 'error' => $e->getMessage()]);

            return null;
        }

        $this->logger->info('config_reloaded', ['file' => $this->_configFile, 'queues' => array_keys($configuration->getContextList()->list)]);

        return $configuration;
    }

    /**
     * Parses the config file and lets the daemon check it.
     *
     * @throws Exception
     * @throws TypeError
     */
    private function _readValidConfiguration(): Configuration
    {
        $configuration = $this->getConfiguration();
        $this->_validateConfiguration($configuration);

        return $configuration;
    }

    /**
     * The daemon's own check of a config it read: it throws to refuse the file. At start the refusal stops the
     * daemon; later the daemon keeps the settings it has. Accepts any config unless a daemon overrides it.
     *
     * Example: FastAnalysis refuses a file with none of the queues P1, P2 and P3.
     *
     * @throws Exception
     */
    protected function _validateConfiguration(Configuration $configuration): void
    {
    }

}