<?php

namespace Utils\AsyncTasks\Workers\Analysis;

use DateTime;
use Exception;
use Model\Analysis\AnalysisDao;
use Model\Analysis\PayableRates as PayableRates;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\TmAnalysisDisabledEvent;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FilesStorageFactory;
use Model\Jobs\JobDao;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\WordCount\CounterModel;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use PDO;
use PDOException;
use Psr\Log\InvalidArgumentException as LogInvalidArgumentException;
use ReflectionException;
use RuntimeException;
use Stomp\Exception\ConnectionException;
use Stomp\Transport\Message;
use Throwable;
use TypeError;
use UnexpectedValueException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Traits\ProjectWordCount;
use Utils\Constants\ProjectStatus as ProjectStatus;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\MMT;
use Utils\Engines\MyMemory;
use Utils\Engines\NONE;
use Utils\Engines\Results\MyMemory\AnalyzeResponse;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractDaemon;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\DaemonTerminatedException;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/12/15
 * Time: 13.05
 *
 */
class FastAnalysis extends AbstractDaemon
{

    use ProjectWordCount;

    /**
     * @throws RuntimeException if queue handler is not initialized
     */
    private function requireQueueHandler(): AMQHandler
    {
        if ($this->queueHandler === null) {
            throw new RuntimeException('Queue handler is not initialized');
        }

        return $this->queueHandler;
    }

    /**
     * Factory seam for the daemon's AMQ handler. Uses a private (non-shared) connection via
     * getNewInstanceForDaemons() so it can be fully replaced on failure and is unaffected by
     * WorkerClient's static-connection close(). Construction is I/O-free (STOMP connects
     * lazily on first send). Overridable in tests.
     *
     * @throws ConnectionException
     */
    protected function _newQueueHandler(): AMQHandler
    {
        return AMQHandler::getNewInstanceForDaemons();
    }

    /**
     * Discard the current AMQ handler and build a fresh one. A zombified Stomp\Client can never
     * self-heal: a single failed connect leaves Client::$isConnecting stuck true, so sendFrame()
     * never reconnects and every send throws "Not connected to any server". A brand-new handler
     * gives a fresh Client AND a fresh Connection, escaping both the stuck flag and the dead
     * socket, so the daemon recovers automatically once the broker is reachable again.
     *
     * This is the single internal entry point for (re)building the handler — the constructor and
     * the failure path both route through here, so _newQueueHandler() (the test seam) has exactly
     * one caller.
     *
     * @return AMQHandler the freshly built handler (also stored in $this->queueHandler)
     * @throws ConnectionException
     * @throws LogInvalidArgumentException
     */
    private function _rebuildQueueHandler(): AMQHandler
    {
        // isset() (not ?->) so this is safe on cold start, when $this->queueHandler is still an
        // uninitialized typed property (the constructor routes through here too).
        if (isset($this->queueHandler)) {
            try {
                $this->queueHandler->close();
            } catch (Throwable $e) {
                // ignore: the whole point is to throw this unhealthy handler away
                $this->logger->debug("Discarding unhealthy AMQ handler: " . $e->getMessage());
            }
        }

        $this->queueHandler = $this->_newQueueHandler();
        $this->logger->debug("AMQ queue handler (re)built.");

        return $this->queueHandler;
    }

    /**
     * Classify a failure as infrastructure/transient versus a data/statement error.
     *
     * Infrastructure/transient (broker unreachable, DB gone away / cannot connect, deadlock,
     * lock-wait timeout, too many connections) should be retried WITHOUT counting toward the
     * per-project park cap, so a transient outage never mass-parks healthy projects. A
     * data/statement error (duplicate key, null in a NOT NULL column, syntax error, missing
     * table) is deterministic "poison" and MUST count, so the project is eventually parked as
     * NOT_TO_ANALYZE instead of looping forever.
     *
     * Differentiation uses PDOException::$errorInfo = [SQLSTATE, driverCode, message]: SQLSTATE
     * class 08 (connection exception) plus a set of MySQL driver codes for an unreachable/gone
     * or contended server. Any other PDOException is treated as a statement error.
     */
    /**
     * A broker-connection failure — thrown directly or wrapped as a previous exception. This is
     * the single detector for "the ActiveMQ broker is unreachable", which both zombifies the
     * Stomp client (→ needs a rebuild) and counts as an infrastructure failure (→ not retried
     * against the cap). Reused by _isInfrastructureFailure so the two never drift apart.
     */
    private function _isBrokerFailure(Throwable $e): bool
    {
        return $e instanceof ConnectionException
            || $e->getPrevious() instanceof ConnectionException;
    }

    private function _isInfrastructureFailure(Throwable $e): bool
    {
        // ActiveMQ broker unreachable
        if ($this->_isBrokerFailure($e)) {
            return true;
        }

        $previous = $e->getPrevious();

        $pdo = null;
        if ($e instanceof PDOException) {
            $pdo = $e;
        } elseif ($previous instanceof PDOException) {
            $pdo = $previous;
        }
        if ($pdo === null) {
            return false;
        }

        $errorInfo  = $pdo->errorInfo ?? [];
        $sqlState   = (string)($errorInfo[0] ?? $pdo->getCode());
        $driverCode = $errorInfo[1] ?? null;

        // SQLSTATE class 08 = connection exception (link failure / server unreachable)
        if (str_starts_with($sqlState, '08')) {
            return true;
        }

        // MySQL driver codes for an unreachable / gone / contended server — transient, retry.
        // NOT data/statement errors (1062 dup key, 1048 null, 1064 syntax, 1146 no table, ...).
        return in_array($driverCode, [
            2002, // CR_CONNECTION_ERROR    — cannot connect (socket)
            2003, // CR_CONN_HOST_ERROR     — cannot connect (host)
            2006, // CR_SERVER_GONE_ERROR   — server gone away
            2013, // CR_SERVER_LOST         — lost connection during query
            2055, // CR_SERVER_LOST_EXTENDED
            1040, // ER_CON_COUNT_ERROR     — too many connections
            1053, // ER_SERVER_SHUTDOWN
            1205, // ER_LOCK_WAIT_TIMEOUT   — transient contention
            1213, // ER_LOCK_DEADLOCK       — transient contention
        ], true);
    }

    /**
     * @var array<int|string, array<string, mixed>>
     */
    protected array $segments;

    /**
     * @var array<string, int|string>
     */
    protected array $segment_hashes;

    /**
     * @var array<string, mixed>
     */
    protected array $actual_project_row;

    /**
     * @var AbstractFilesStorage
     */
    protected AbstractFilesStorage $files_storage;

    private IDatabase $db;

    /**
     * The DB handle for this daemon, injected by the entry point
     * (daemons/FastAnalysis.php) via {@see self::setDatabase()} after
     * Bootstrap::start(), then threaded into the DAOs this daemon builds. This
     * class never resolves the connection itself — neither Database::obtain()
     * nor Bootstrap::getDatabase().
     */
    protected function db(): IDatabase
    {
        return $this->db;
    }

    /**
     * Inject the per-process DB handle. Called by the daemon entry point
     * (composition root) immediately after Bootstrap::start().
     */
    public function setDatabase(IDatabase $db): void
    {
        $this->db = $db;
    }

    private ?ProjectDao $projectDao = null;

    private function getProjectDao(): ProjectDao
    {
        return $this->projectDao ??= new ProjectDao($this->db());
    }

    const int ERR_NO_SEGMENTS = 127;
    const int ERR_TOO_LARGE = 128;
    const int ERR_500 = 129;
    const int ERR_EMPTY_RESPONSE = 130;
    // A non-200 fast-analysis outcome that should be retried, not stalled at FAST_OK.
    // TRANSIENT (0 = transport error, 503/other 5xx = overload) is an outage → retried uncounted so
    // it does not mass-park projects; FAILED (4xx = malformed/misconfigured request) is per-project
    // poison → counted toward the attempt cap. Fast analysis has no auth/rate-limit, so 401/403/429
    // do not occur.
    const int ERR_ANALYSIS_TRANSIENT = 131;
    const int ERR_ANALYSIS_FAILED = 132;

    // Actions returned by _decideFetchFailureAction() and dispatched by main() when a fetch throws.
    const string ACTION_PARK = 'park';                       // NOT_TO_ANALYZE (project can't be analyzed)
    const string ACTION_RESET = 'reset';                     // back to NEW + release lock (dead ERR_EMPTY_RESPONSE)
    const string ACTION_RETRY_COUNTED = 'retry_counted';     // release for retry, count toward the cap
    const string ACTION_RETRY_UNCOUNTED = 'retry_uncounted'; // release for retry, do not count (transient/infra)
    const string ACTION_DONE = 'done';                       // finalize DONE (pre-translated, or disabled project)

    /**
     * Max consecutive countable (non-infrastructure) fast-analysis failures for a single
     * project before it is parked as NOT_TO_ANALYZE, so a poison project cannot loop forever.
     */
    const int MAX_FAST_ANALYSIS_ATTEMPTS = 5;

    /** Redis key prefix for the per-project fast-analysis failed-attempt counter. */
    const string FAILED_ATTEMPTS_KEY_PREFIX = '_fAttempts:';

    /** Redis key prefix for the per-project fast-analysis processing lock. */
    const string PROCESSING_LOCK_KEY_PREFIX = '_fPid:';

    /**
     * Reload Configuration every cycle
     *
     * @throws Exception
     * @throws \TypeError
     */
    protected function _updateConfiguration(): void
    {
        $configuration = $this->getConfiguration();

        //First Execution, load build object
        $this->_queueContextList = $configuration->getContextList();
    }

    /**
     * @throws PDOException
     * @throws LogInvalidArgumentException
     */
    protected function _checkDatabaseConnection(): void
    {
        $db = $this->db();
        if (!$db instanceof Database) {
            return;
        }
        try {
            // Probe inside a transaction so ProxySQL routes the health check to the master —
            // the same route the lock/BUSY writes actually use. A bare ping() is a plain read a
            // replica can answer while the master is down, giving a false green.
            $db->transaction(function () use ($db) {
                $db->ping();
            });
        } catch (Throwable $e) {
            $this->logger->debug($e->getMessage() . " - Trying to close and reconnect.");
            $db->close();
            //reconnect
            $db->getConnection();
        }
    }

    /**
     * @throws \TypeError
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws LogInvalidArgumentException
     */
    protected function __construct(string $configFile = null, ?string $contextIndex = null)
    {
        parent::__construct($configFile, $contextIndex);

        $this->logger = LoggerFactory::getLogger('fast_analysis', 'fastAnalysis.log');
        if (AppConfig::$DEBUG) {
            $this->logger->pushHandler((new StreamHandler('php://stdout'))->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n")));
        }
        LoggerFactory::setAliases(['engines'], $this->logger);

        try {
            // Own a private (non-shared) connection so it can be fully rebuilt on failure and is
            // not torn down by WorkerClient's static-connection close(). _rebuildQueueHandler() is
            // the single (re)build entry point (safe on this cold start via its isset() guard).
            $this->_rebuildQueueHandler()->getRedisClient()->sadd(RedisKeys::FAST_PID_SET, [$this->myProcessPid . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID]);

            $this->_updateConfiguration();
        } catch (Exception $ex) {
            $this->logger->debug(str_pad(" " . $ex->getMessage() . " ", 60, "*", STR_PAD_BOTH));
            $this->logger->debug(str_pad("EXIT", 60, " ", STR_PAD_BOTH));
            if (AppConfig::$ENV === 'testing') {
                throw new DaemonTerminatedException();
            }
            die();
        }

        $this->files_storage = FilesStorageFactory::create();
    }

    /**
     * @param array<int, string>|null $args
     *
     * @return void
     * @throws Throwable
     */
    public function main(array $args = null): void
    {
        do {
            if (!$this->requireQueueHandler()->getRedisClient()->sismember(RedisKeys::FAST_PID_SET, $this->myProcessPid . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID)) {
                // suicide gracefully
                $this->RUNNING = false;
                continue;
            }

            try {
                $this->_checkDatabaseConnection();
                $projects_list = $this->_getLockProjectForVolumeAnalysis(5);
            } catch (PDOException $e) {
                $this->logger->debug($e->getMessage() . " - Error again. Try to reconnect in next cycle.");
                sleep(3); // wait for reconnection
                continue; // next cycle, reload projects.
            }

            if (empty($projects_list)) {
//                $this->logger->debug( "No projects: wait 3 seconds." );
                sleep(3);
                continue;
            }

            $this->logger->debug("Projects found", ['projects' => $projects_list]);

            $featureSet = new FeatureSet($this->db());

            foreach ($projects_list as $project_row) {
                $this->actual_project_row = $project_row;

                $pid = (int)$this->actual_project_row['id'];
                $this->logger->debug("Analyzing $pid, querying data...");

                try {
                    $perform_Tms_Analysis = true;
                    $status = ProjectStatus::STATUS_FAST_OK;

                    // disable TM analysis

                    $disable_Tms_Analysis = $this->actual_project_row['id_tms'] == 0 && $this->actual_project_row['id_mt_engine'] == 0;

                    if ($disable_Tms_Analysis) {
                        /**
                         * MyMemory disabled and MT Disabled Too
                         * So don't perform TMS Analysis ( don't send segments in queue ), only fill segment_translation table
                         */
                        $perform_Tms_Analysis = false;
                        $status = ProjectStatus::STATUS_DONE;

                        $featureSet->dispatch(new TmAnalysisDisabledEvent($pid));

                        $this->logger->debug('Perform Analysis FALSE');
                    }

                    try {
                        $fastReport = $this->_fetchMyMemoryFast($pid);
                        $this->logger->debug("Fast $pid result: " . count($fastReport->responseData) . " segments.");
                    } catch (Exception $e) {
                        // All decision logic lives in _decideFetchFailureAction() (unit-tested); here we
                        // only dispatch the returned action.
                        switch ($this->_decideFetchFailureAction($e, $perform_Tms_Analysis)) {
                            case self::ACTION_PARK:
                                self::_updateProject($pid, ProjectStatus::STATUS_NOT_TO_ANALYZE);
                                continue 2; // next project
                            case self::ACTION_RESET:
                                // dead ERR_EMPTY_RESPONSE path, kept to remember how to reset the status
                                $this->logger->debug($e->getMessage());
                                self::_updateProject($pid, ProjectStatus::STATUS_NEW);
                                $this->requireQueueHandler()->getRedisClient()->del(['_fPid:' . $pid]);
                                sleep(3);
                                continue 2; // next project
                            case self::ACTION_RETRY_COUNTED:
                                $this->logger->error($e->getMessage() . " Releasing pid $pid for retry.");
                                $this->_releaseFailedProject($pid, $e->getMessage());
                                continue 2; // next project
                            case self::ACTION_RETRY_UNCOUNTED:
                                $this->logger->error($e->getMessage() . " Releasing pid $pid for retry (uncounted).");
                                $this->_releaseFailedProject($pid, $e->getMessage(), false);
                                continue 2; // next project
                            case self::ACTION_DONE:
                            default:
                                // pre-translated, or a disabled project: finalize DONE
                                $status = ProjectStatus::STATUS_DONE;
                                break;
                        }
                    }

                    // Past here $fastReport is set only on a 200 response; otherwise an exception already
                    // decided the terminal status (DONE) and we proceed with empty data.
                    $fastResultData = isset($fastReport) ? $fastReport->responseData : [];

                    unset($fastReport);

                    foreach ($fastResultData as $k => $v) {
                        if ($v['type'] == "50%-74%") {
                            $fastResultData[$k]['type'] = "NO_MATCH";
                        }

                        $this->segments[$this->segment_hashes[$k]]['wc'] = $fastResultData[$k]['wc'];
                        $this->segments[$this->segment_hashes[$k]]['match_type'] = strtoupper($fastResultData[$k]['type']);
                    }
                    //clean the reverse lookup array
                    $this->segment_hashes = [];

                    // INSERT DATA
                    $this->logger->debug("Inserting segments...");

                    $projectStruct = new ProjectStruct();

                    // Infrastructure/transient failures (broker or DB unreachable, deadlock, ...)
                    // must not count toward the per-project attempt cap, so an outage does not
                    // mass-park projects. A broker-connection failure additionally needs the STOMP
                    // handler rebuilt (the client zombifies); a DB failure does not.
                    $insertFailureIsInfra  = false;
                    $insertFailureIsBroker = false;

                    try {
                        /**
                         * Ensure we have fresh data from the master node
                         */
                        $metadataResult = $this->db()->transaction(function () use ($pid) {
                            $projectStruct = $this->getProjectDao()->findById($pid);
                            if ($projectStruct === null) {
                                return null;
                            }

                            return [
                                'project' => $projectStruct,
                                'metadata' => (new ProjectMetadataDao($this->db()))
                                    ->setCacheTTL(3600)
                                    ->allByProjectIdAsKeyValue((int)$projectStruct->id),
                            ];
                        });

                        if ($metadataResult === null) {
                            // The project row no longer exists on master (deleted mid-analysis): it
                            // will not reappear, so skip it. There is no row to hold a BUSY status;
                            // just drop the (now inert) processing lock left by the picker.
                            $this->logger->error("Fast analysis skipped: project $pid no longer exists.");
                            $this->requireQueueHandler()->getRedisClient()->del([self::PROCESSING_LOCK_KEY_PREFIX . $pid]);
                            continue;
                        }

                        $projectStruct = $metadataResult['project'];
                        $allMetadata = $metadataResult['metadata'];
                        $projectFeaturesString = $allMetadata[ProjectsMetadataMarshaller::FEATURES_KEY->value] ?? '';
                        $mt_evaluation = isset($allMetadata[ProjectsMetadataMarshaller::MT_EVALUATION->value])
                            ? (bool)$allMetadata[ProjectsMetadataMarshaller::MT_EVALUATION->value]
                            : null;
                        $mt_qe_workflow_enabled = isset($allMetadata[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value])
                            ? (bool)$allMetadata[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value]
                            : null;
                        $mt_qe_workflow_parameters_raw = $allMetadata[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value] ?? null;
                        $mt_qe_workflow_parameters_decoded = $mt_qe_workflow_parameters_raw !== null
                            ? json_decode($mt_qe_workflow_parameters_raw, true)
                            : null;
                        $mt_qe_workflow_parameters = is_array($mt_qe_workflow_parameters_decoded)
                            ? new MTQEWorkflowParams($mt_qe_workflow_parameters_decoded)
                            : null;
                        $mt_quality_value_in_editor = (int)($allMetadata[ProjectsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value] ?? 85);
                        $subfiltering_handlers = $allMetadata[ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value] ?? [];
                        $subfiltering_handlers = is_array($subfiltering_handlers) ? $subfiltering_handlers : [];
                        $icu_enabled = (bool)($allMetadata[ProjectsMetadataMarshaller::ICU_ENABLED->value] ?? false);

                        $insertReportRes = $this->_insertFastAnalysis(
                            $projectStruct,
                            $projectFeaturesString,
                            PayableRates::$DEFAULT_PAYABLE_RATES,
                            $featureSet,
                            $perform_Tms_Analysis,
                            $mt_evaluation,
                            $mt_qe_workflow_enabled,
                            $mt_qe_workflow_parameters,
                            $mt_quality_value_in_editor,
                            $subfiltering_handlers,
                            $icu_enabled
                        );
                    } catch (Throwable $e) {
                        $insertReportRes = -1;
                        // Transient infrastructure (broker/DB unreachable, deadlock) vs poison data
                        // (dup key, null, syntax) — only the former is retried without counting.
                        $insertFailureIsInfra  = $this->_isInfrastructureFailure($e);
                        // Only a broker-connection failure zombifies the Stomp client and needs a rebuild.
                        $insertFailureIsBroker = $this->_isBrokerFailure($e);
                        $this->logger->debug($e->getMessage() . " " . $e->getTraceAsString());
                    }

                    if ($insertReportRes < 0) {
                        // Reverse the BUSY status + _fPid lock so the project is re-picked next
                        // cycle instead of being stranded. On retry _insertFastAnalysis re-queues
                        // only the not-yet-analyzed segments (see _getAlreadyAnalyzedSegments).
                        $this->logger->debug("InsertFastAnalysis failed for pid $pid. Releasing for retry.");
                        // $insertFailureIsInfra indicates if the failure was caused by infrastructure issues (e.g. broker unreachable).
                        // It is used to decide whether to increment the failure counter: infra failures don't count toward the limit.
                        $this->_releaseFailedProject($pid, 'insert/publish failed', !$insertFailureIsInfra);
                        // A broker-connection failure zombifies the Stomp client for the life of the
                        // process; rebuild it (the Redis ops above use a separate connection and still
                        // work) so this cycle's remaining projects — and the released one on its retry —
                        // use a healthy handler instead of a permanently stuck one. A DB failure does
                        // not touch the Stomp client, so it must not trigger a rebuild.
                        if ($insertFailureIsBroker) {
                            $this->_rebuildQueueHandler();
                        }
                        continue;
                    }

                    $this->logger->debug("done");
                    // INSERT DATA

                    self::_updateProject($pid, $status);
                    // processing succeeded: clear the failed-attempt counter
                    $this->requireQueueHandler()->getRedisClient()->del([self::FAILED_ATTEMPTS_KEY_PREFIX . $pid]);
                    $fs = $this->files_storage;
                    $fs->deleteFastAnalysisFile((string)$pid);

                    (new JobDao($this->db()))->destroyCacheByProjectId($pid);
                    (new ProjectDao($this->db()))->destroyFetchByIdCache($pid, ProjectStruct::class);
                    $this->getProjectDao()->destroyCacheByIdAndPassword($pid, $projectStruct->password);
                    (new AnalysisDao($this->db()))->destroyCacheByProjectId($pid);
                } catch (Throwable $e) {
                    // U3 safety net: an unexpected Error/TypeError raised anywhere while
                    // processing this project (malformed MyMemory payload, metadata parse,
                    // status write, cache purge, ...) must not escape and kill the daemon,
                    // which would strand every project locked in this batch as BUSY. Recover
                    // just this one and carry on with the next.
                    $this->_recoverFromUnexpectedFailure($pid, $e);
                }
            }
        } while ($this->RUNNING);

        $this->cleanShutDown();
    }

    /**
     * @param int $pid
     *
     * @return AnalyzeResponse
     * @throws Exception
     * @throws TypeError
     */
    protected function _fetchMyMemoryFast(int $pid): AnalyzeResponse
    {
        // S4: reset per-project state up front. These members are reused across the daemon loop, so
        // a throw before they are reassigned (e.g. the EnginesFactory failure below) must not leave
        // the PREVIOUS project's segments visible to _insertFastAnalysis (cross-project contamination).
        $this->segments       = [];
        $this->segment_hashes = [];

        $myMemory = EnginesFactory::getInstance(1, $this->db(), MyMemory::class);
        if (!$myMemory instanceof MyMemory) {
            throw new Exception("Expected MyMemory engine for id=1, got " . $myMemory::class);
        }

        $fs = $this->files_storage;

        try {
            $this->logger->debug("Fetching data from disk");
            $this->segments = $fs->getFastAnalysisData($pid);
        } catch (UnexpectedValueException) {
            $this->logger->debug("Error Fetching data from disk. Fallback to database.");

            try {
                $this->segments = $this->_getSegmentsForFastVolumeAnalysis($pid);
            } catch (PDOException) {
                throw new Exception("Error Fetching data for Project. Too large. Skip.", self::ERR_TOO_LARGE);
            }
        }

        if (count($this->segments) == 0) {
            //there is no analysis on that file, it is ALL Pre-Translated
            $exceptionMsg = 'There is no analysis on that file, it is ALL Pre-Translated';
            $this->logger->debug($exceptionMsg);
            throw new Exception($exceptionMsg, self::ERR_NO_SEGMENTS);
        }

        //compose a lookup array
        $this->segment_hashes = [];

        $total_source_words = 0;
        $fastSegmentsRequest = [];
        foreach ($this->segments as $pos => $segment) {
            $fastSegmentsRequest[$pos]['jsid'] = $segment['jsid'];
            $fastSegmentsRequest[$pos]['segment'] = $segment['segment'];
            $fastSegmentsRequest[$pos]['segment_hash'] = $segment['segment_hash'];
            $fastSegmentsRequest[$pos]['source'] = $segment['source'];
            $fastSegmentsRequest[$pos]['count'] = $segment['raw_word_count'];

            //set a reverse lookup array to get the right segment is by its position
            $this->segment_hashes[$segment['jsid']] = $pos;

            $total_source_words += $segment['raw_word_count'];
            if ($total_source_words > AppConfig::$MAX_SOURCE_WORDS) {
                throw new Exception("Project too large. Skip.", self::ERR_TOO_LARGE);
            }
        }

        $this->logger->debug("Done.");
        $this->logger->debug("Pid $pid: " . count($this->segments) . " segments");
        $this->logger->debug("Sending query to Matches analysis...");

        $result = $myMemory->fastAnalysis(array_values($fastSegmentsRequest));

        // Single classifier: return on 200, otherwise throw a typed code so main() never has to
        // inspect the status (and never falls through to zero-wc processing → FAST_OK stall).
        $this->_assertFastAnalysisSucceeded((int)$result->responseStatus, $result->error->code ?? null, $pid);

        return $result;
    }

    /**
     * Classify a fast-analysis response: return quietly on HTTP 200, otherwise throw a typed code.
     * Fast analysis has no authentication and no rate limit, so 401/403/429 do not occur; the
     * realistic non-200 set is 0 (transport error), 4xx (malformed/misconfigured request) and 5xx
     * (server/gateway).
     *
     * @param int             $responseStatus HTTP status (0 when the transport failed before a response)
     * @param int|string|null $errorCode      curl error code (negated errno), if any
     * @param int             $pid
     *
     * @return void
     * @throws Exception ERR_TOO_LARGE | ERR_500 | ERR_ANALYSIS_TRANSIENT | ERR_ANALYSIS_FAILED
     */
    protected function _assertFastAnalysisSucceeded(int $responseStatus, int|string|null $errorCode, int $pid): void
    {
        if ($responseStatus === 200) {
            return;
        }

        if ($errorCode == -28 || $responseStatus === 504) { // curl / gateway timeout
            throw new Exception("Fast analysis timed out (pid $pid).", self::ERR_TOO_LARGE);
        }
        if ($responseStatus === 500 || $responseStatus === 502) { // upstream server error
            throw new Exception("Fast analysis server error (pid $pid, status $responseStatus).", self::ERR_500);
        }

        // 0 = transport failure (connection refused / DNS / SSL / reset), 503 / other 5xx = overload:
        // a transient outage → retried WITHOUT counting toward the cap, so an outage does not
        // mass-park projects. Any remaining 4xx is a malformed/misconfigured request → counted.
        if ($responseStatus === 0 || $responseStatus >= 500) {
            throw new Exception("Fast analysis transient failure (pid $pid, status $responseStatus).", self::ERR_ANALYSIS_TRANSIENT);
        }

        throw new Exception("Fast analysis client error (pid $pid, status $responseStatus).", self::ERR_ANALYSIS_FAILED);
    }

    /**
     * Decide how main() must react when _fetchMyMemoryFast() throws. All fetch-failure decision
     * logic is centralised (and unit-tested) here; main() only dispatches the returned action.
     *
     * @param Throwable $e                  the exception thrown by the fetch
     * @param bool      $performTmsAnalysis whether TM/MT analysis is enabled for this project
     *
     * @return string one of the self::ACTION_* tokens
     */
    protected function _decideFetchFailureAction(Throwable $e, bool $performTmsAnalysis): string
    {
        switch ($e->getCode()) {
            case self::ERR_TOO_LARGE:
            case self::ERR_500:
                return self::ACTION_PARK;

            case self::ERR_EMPTY_RESPONSE:
                return self::ACTION_RESET;

            case self::ERR_ANALYSIS_TRANSIENT:
                return $performTmsAnalysis ? self::ACTION_RETRY_UNCOUNTED : self::ACTION_DONE;

            case self::ERR_ANALYSIS_FAILED:
                return $performTmsAnalysis ? self::ACTION_RETRY_COUNTED : self::ACTION_DONE;

            case self::ERR_NO_SEGMENTS:
                // all segments pre-translated → nothing to analyze, legitimately DONE
                return self::ACTION_DONE;

            default:
                // S3: a truly unexpected error (engine build, decode, library fault, …). Never fake
                // DONE when analysis is enabled — retry instead (infra/transient uncounted, real
                // fault counted so it eventually parks). Disabled projects keep the prior DONE.
                if (!$performTmsAnalysis) {
                    return self::ACTION_DONE;
                }

                return $this->_isInfrastructureFailure($e) ? self::ACTION_RETRY_UNCOUNTED : self::ACTION_RETRY_COUNTED;
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws LogInvalidArgumentException
     * @throws Exception
     */
    public function cleanShutDown(): void
    {
        $this->RUNNING = false;
        $this->myProcessPid = 0;

        //SHUTDOWN
        $this->requireQueueHandler()->getRedisClient()->srem(RedisKeys::FAST_PID_SET, getmypid() . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID);

        $msg = str_pad(" FAST ANALYSIS " . getmypid() . ":" . gethostname() . ":" . AppConfig::$INSTANCE_ID . " HALTED GRACEFULLY ", 50, "-", STR_PAD_BOTH);
        $this->logger->debug($msg);

        $this->requireQueueHandler()->getRedisClient()->disconnect();

        $this->requireQueueHandler()->getClient()->disconnect();
        $this->queueHandler = null;
    }

    /**
     * @throws PDOException
     * @throws LogInvalidArgumentException
     */
    protected function _updateProject(int $pid, string $status): void
    {
        // Atomic conditional write: never overwrite a DONE set by a concurrent TM worker. A late
        // FAST_OK/BUSY write here would strand the project "analyzing" forever. One conditional
        // UPDATE is routed to the master by ProxySQL (it is a write), so there is no read-then-write
        // race and no transaction is needed — unlike the previous select-then-update, whose
        // non-locking snapshot read left a lost-update window open under REPEATABLE READ.
        $affected = $this->getProjectDao()->changeProjectStatusIfNotDone($pid, $status);
        if ($affected === 0) {
            // 0 rows = the project is already DONE (a TM worker finished first) or gone. Skipping is
            // correct; logging keeps the concurrency observable instead of silently swallowed.
            $this->logger->debug("*** Project $pid: status update to '$status' matched 0 rows — already DONE (a TM worker finished first) or the project is gone; skipping.");
        } else {
            $this->logger->debug("*** Project $pid: $status");
        }
    }

    /**
     * Reverse the BUSY status + processing lock taken in _getLockProjectForVolumeAnalysis
     * so a project that failed mid-processing is not stranded forever. The picker selects
     * only STATUS_NEW and the _fPid lock would otherwise block re-pickup for its full 24h
     * TTL, so both must be undone here.
     *
     * The project is released back to NEW for a later retry. A project that keeps failing on
     * countable (non-infrastructure) errors is parked as NOT_TO_ANALYZE once it reaches
     * MAX_FAST_ANALYSIS_ATTEMPTS, so a poison project cannot loop forever. Infrastructure
     * failures (e.g. the broker being unreachable) pass $countTowardCap = false and do NOT
     * increment the counter, so a transient outage never mass-parks healthy projects.
     *
     * @param int $pid The project ID of the failed project.
     * @param string $reason The reason for the failure.
     * @param bool $countTowardCap Whether to increment the failure attempts counter for the project. Default is true.
     *
     * @return void
     * @throws ReflectionException
     * @throws Throwable
     */
    private function _releaseFailedProject(int $pid, string $reason, bool $countTowardCap = true): void
    {
        $redis       = $this->requireQueueHandler()->getRedisClient();
        $attemptsKey = self::FAILED_ATTEMPTS_KEY_PREFIX . $pid;

        if ($countTowardCap) {
            $attempts = (int)$redis->incr($attemptsKey);
            $redis->expire($attemptsKey, 60 * 60 * 24);
        } else {
            $attempts = (int)$redis->get($attemptsKey);
        }

        if ($countTowardCap && $attempts >= self::MAX_FAST_ANALYSIS_ATTEMPTS) {
            $this->logger->error("Fast analysis pid $pid failed $attempts times ($reason). Parking as NOT_TO_ANALYZE.");
            $this->_updateProject($pid, ProjectStatus::STATUS_NOT_TO_ANALYZE);
            $redis->del([$attemptsKey]);
        } else {
            $this->logger->debug("Fast analysis pid $pid failed ($reason), attempt $attempts. Releasing to NEW for retry.");
            $this->_updateProject($pid, ProjectStatus::STATUS_NEW);
        }

        // release the processing lock in both branches so the next cycle can re-pick it
        $redis->del([self::PROCESSING_LOCK_KEY_PREFIX . $pid]);
    }

    /**
     * Last-resort recovery for an *unexpected* Throwable raised while processing a single project
     * (a malformed MyMemory payload, a metadata parse fault, a status write or cache purge blowing
     * up, ...). Without this net an Error/TypeError would escape main()'s per-project handling,
     * kill the daemon, and strand every project locked in the current batch as BUSY — the picker
     * only takes NEW rows and the _fPid lock blocks re-pickup for its full 24h TTL.
     *
     * The project is released for a later retry; infrastructure failures do not count toward the
     * park cap (see _releaseFailedProject), so a transient outage never mass-parks healthy projects
     * while a genuinely poison project still parks after MAX_FAST_ANALYSIS_ATTEMPTS.
     *
     * @param int $pid The project being processed when the failure occurred.
     * @param Throwable $e The unexpected failure.
     *
     * @return void
     * @throws ReflectionException
     * @throws Throwable
     */
    private function _recoverFromUnexpectedFailure(int $pid, Throwable $e): void
    {
        $this->logger->error(
            "Unexpected failure while processing fast analysis for pid $pid: "
            . $e->getMessage() . "\n" . $e->getTraceAsString()
        );

        $this->_releaseFailedProject($pid, $e->getMessage(), !$this->_isInfrastructureFailure($e));
    }

    /**
     * Return the set of (segment, job) pairs to SKIP when publishing, as a lookup map keyed
     * "id_segment:id_job". Deliberately cheap on the common path: on the first attempt nothing
     * has been analyzed yet, so the .ser payload already is the authoritative to-do list — we
     * publish it whole and issue NO DB query (this is exactly why the .ser exists).
     *
     * Only on a retry (the S1a _fAttempts counter is > 0, i.e. a previous attempt failed) do we
     * read, from the master node, the rows the TM workers already finished. The key is
     * "id_segment:id_job", NOT id_segment alone: the same source segment is inserted once per
     * job and published once per job/target-language (segment_translations PK is
     * (id_segment, id_job)), and each language must be analyzed — a per-segment skip would drop
     * the second language.
     *
     * This is a retry traffic optimisation, not a correctness requirement: re-publishing is
     * already absorbed idempotently by the TM worker (PR #4670). The DB is touched only after a
     * failure, never on the common first-attempt path.
     *
     * @return array<string, true> membership map "id_segment:id_job" => true (test with isset())
     * @throws Throwable
     */
    private function _getAlreadyAnalyzedSegments(int $pid): array
    {
        // first attempt → nothing analyzed yet → publish the whole .ser, no DB query
        if ((int)$this->requireQueueHandler()->getRedisClient()->get(self::FAILED_ATTEMPTS_KEY_PREFIX . $pid) === 0) {
            return [];
        }

        return $this->db()->transaction(function () use ($pid): array {
            $stmt = $this->db()->getConnection()->prepare(
                "SELECT st.id_segment, st.id_job
                   FROM segment_translations st
                   JOIN jobs j ON j.id = st.id_job
                  WHERE j.id_project = :pid
                    AND st.tm_analysis_status IN ('DONE', 'SKIPPED')"
            );
            $stmt->execute([':pid' => $pid]);

            $alreadyAnalyzed = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $alreadyAnalyzed[(int)$row['id_segment'] . ':' . (int)$row['id_job']] = true;
            }
            $stmt->closeCursor();

            return $alreadyAnalyzed;
        });
    }

    /**
     * @param list<string> $tuple_list
     * @param list<mixed> $bind_values
     *
     * @throws PDOException
     * @throws LogInvalidArgumentException
     */
    protected function _executeInsert(array $tuple_list, array $bind_values): void
    {
        $db = $this->db();
        $query_st = "INSERT INTO `segment_translations` ( 
                                      id_job, 
                                      id_segment, 
                                      segment_hash, 
                                      match_type, 
                                      eq_word_count, 
                                      standard_word_count 
                                 ) VALUES "
            . implode(", ", $tuple_list) .
            " ON DUPLICATE KEY UPDATE
                        match_type          = IF( tm_analysis_status = 'SKIPPED', match_type, VALUES( match_type ) ),
                        eq_word_count       = IF( tm_analysis_status = 'SKIPPED', eq_word_count, VALUES( eq_word_count ) ),
                        standard_word_count = IF( tm_analysis_status = 'SKIPPED', standard_word_count, VALUES( standard_word_count ) )
                ";

        $this->logger->debug("Executed " . (count($tuple_list)));
        $stmt = $db->getConnection()->prepare($query_st);
        $stmt->execute($bind_values);
        $stmt->closeCursor();
    }

    /**
     * @param ProjectStruct $projectStruct
     * @param string $projectFeaturesString
     * @param array<string, int|float> $equivalentWordMapping
     * @param FeatureSet $featureSet
     * @param bool $perform_Tms_Analysis
     * @param bool|null $mt_evaluation
     * @param bool|null $mt_qe_workflow_enabled
     * @param MTQEWorkflowParams|null $mt_qe_workflow_parameters
     * @param int|null $mt_quality_value_in_editor
     * @param array<int, string>|null $subfiltering_handlers
     * @param bool $icu_enabled
     * @return int
     * @throws Throwable
     */
    protected function _insertFastAnalysis(
        ProjectStruct       $projectStruct,
        string              $projectFeaturesString,
        array               $equivalentWordMapping,
        FeatureSet          $featureSet,
        bool                $perform_Tms_Analysis = true,
        ?bool               $mt_evaluation = false,
        ?bool               $mt_qe_workflow_enabled = false,
        ?MTQEWorkflowParams $mt_qe_workflow_parameters = null,
        ?int                $mt_quality_value_in_editor = 85,
        ?array              $subfiltering_handlers = [],
        bool                $icu_enabled = false
    ): int
    {
        $pid = $projectStruct->id;
        if ($pid === null) {
            throw new RuntimeException('ProjectStruct has no ID');
        }
        $total_eq_wc = 0;
        $total_standard_wc = 0;

        $tuple_list = [];
        $bind_values = [];
        $totalSegmentsToAnalyze = 0;
        foreach ($this->segments as $k => $v) {
            $jid_pass = explode("-", $v['jsid']);

            // only to remember the meaning of $k
            // EX: 21529088-42593:b433193493c6,42594:b4331aacf3d4
            //$id_segment = $jid_fid[ 0 ];

            $list_id_jobs_password = $jid_pass[1];

            [$eq_word, $standard_words, $match_type] = $this->_getWordCountForSegment($v, $equivalentWordMapping);

            $total_eq_wc += $eq_word;
            /** @noinspection PhpUnusedLocalVariableInspection */
            $total_standard_wc += $standard_words;

            $list_id_jobs_password = explode(',', $list_id_jobs_password);
            foreach ($list_id_jobs_password as $id_job) {
                [$id_job,] = explode(":", $id_job);

                $bind_values[] = (int)$id_job;
                $bind_values[] = (int)$v['id'];
                $bind_values[] = $v['segment_hash'];
                $bind_values[] = $match_type;
                $bind_values[] = ((float)$eq_word > $v['raw_word_count']) ? $v['raw_word_count'] : (float)$eq_word;
                $bind_values[] = ((float)$standard_words > $v['raw_word_count']) ? $v['raw_word_count'] : (float)$standard_words;

                $tuple_list[] = "( ?,?,?,?,?,? )";
                $totalSegmentsToAnalyze++;

                //WE TRUST ON THE FAST ANALYSIS RESULTS FOR THE WORD COUNT
                //here we are pruning the segments that must not be sent to the engines for the TM analysis
                //because we multiply the word_count with the equivalentWordMapping ( and this can be 0 for some values )
                //we must check if the value of $fastReport[ $k ]['wc'] and not $data[ 'eq_word_count' ]
                if ($this->segments[$k]['wc'] > 0 && $perform_Tms_Analysis) {
                    /**
                     *
                     * IMPORTANT
                     * id_job will be taken from languages ( 80415:fr-FR,80416:it-IT )
                     */
                    $this->segments[$k]['pid'] = $pid;
                    $this->segments[$k]['ppassword'] = $projectStruct->password;
                    $this->segments[$k]['date_insert'] = (new DateTime())->format('Y-m-d H:i:s');
                    $this->segments[$k]['eq_word_count'] = ((float)$eq_word > $v['raw_word_count']) ? $v['raw_word_count'] : (float)$eq_word;
                    $this->segments[$k]['standard_word_count'] = ((float)$standard_words > $v['raw_word_count']) ? $v['raw_word_count'] : (float)$standard_words;
                    $this->segments[$k]['match_type'] = $match_type;
                    $this->segments[$k]['fast_exact_match_type'] = $v['match_type'];
                } elseif ($perform_Tms_Analysis) {
                    LoggerFactory::doJsonLog('Skipped Fast Segment: ' . var_export($this->segments[$k], true));
                    // this segment must not be sent to the TM analysis queue
                    unset($this->segments[$k]);
                } else {
                    //In this case the TM analysis is disabled
                    //ALL segments must not be sent to the TM analysis queue
                    //do nothing, but $perform_Tms_Analysis is false, so we want delete all elements after the end of the loop
                }

                if (($totalSegmentsToAnalyze % 200) == 0) {
                    try {
                        $this->_executeInsert($tuple_list, $bind_values);
                    } catch (PDOException $e) {
                        $this->logger->debug($e->getMessage());

                        return $e->getCode() * -1;
                    }
                    $tuple_list = [];
                    $bind_values = [];
                }
            }

            //anyway, this key must be removed because he is no more needed and we want not to send it to the queue
            unset($this->segments[$k]['wc']);
            if (!$perform_Tms_Analysis) {
                unset($this->segments[$k]);
            }
        }

        if (($totalSegmentsToAnalyze % 200) != 0) {
            try {
                $this->_executeInsert($tuple_list, $bind_values);
            } catch (PDOException $e) {
                $this->logger->debug($e->getMessage());

                return $e->getCode() * -1;
            }
        }

        unset($tuple_list);

        $data2 = ['fast_analysis_wc' => $total_eq_wc];
        $where = ["id" => $pid];

        try {
            $project_creation_success = $this->db()->transaction(function () use ($perform_Tms_Analysis, $pid, $data2, $where) {
                /*
                 * IF NO TM ANALYSIS, update the jobs global word count
                 */
                if (!$perform_Tms_Analysis) {
                    $_details = $this->getProjectSegmentsTranslationSummary($pid);

                    $this->logger->debug("--- trying to initialize job total word count.");

                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $query_rollup = array_pop($_details); //Don't remove, needed to remove rollup row

                    foreach ($_details as $job_info) {
                        $counter = new CounterModel($this->db());
                        $counter->initializeJobWordCount($job_info['id_job'], $job_info['password']);
                    }
                }
                /* IF NO TM ANALYSIS, upload the jobs global word count */

                return $this->db()->update('projects', $data2, $where);
            });
        } catch (PDOException $e) {
            $this->logger->debug($e->getMessage());

            return $e->getCode() * -1;
        }

        $engine = EnginesFactory::getInstance($this->actual_project_row['id_mt_engine'], $this->db(), AbstractEngine::class);
        if ($engine->isAdaptiveMT()) {
            $engine->syncMemories($this->actual_project_row, array_values($this->segments));
        }

        /*
         * The $fastResultData[0]['id_mt_engine'] is the index of the MT engine we must use.
         *
         * I take the value from the first element of the list (the last one is the same for the project),
         * because surely this value is equal for all the record of the project.
         */
        $queueInfo = $this->_getQueueAddressesByPriority($totalSegmentsToAnalyze, $this->actual_project_row['id_mt_engine']);

        if ($queueInfo === null) {
            $this->logger->debug("No queue address found for project $pid. Skipping enqueue.");

            return $project_creation_success;
        }

        if ($totalSegmentsToAnalyze) {
            $this->logger->debug("Publish Segment Translations to the queue --> $queueInfo->queue_name: $totalSegmentsToAnalyze");
            $this->logger->debug("Elements: $totalSegmentsToAnalyze");

            try {
                $this->_setTotal(['pid' => $pid, 'queueInfo' => $queueInfo]);
            } catch (Exception $e) {
                $this->logger->debug($e->getMessage() . " " . $e->getTraceAsString());
                throw $e;
            }

            $time_start = microtime(true);

            /**
             * Reset the indexes of the list to get the context easily
             */
            $this->segments = array_values($this->segments);

            /**
             * Idempotent publish (cheap): on the first attempt this is empty and we publish
             * the whole .ser; only on a retry does it hold the id_segments already analyzed
             * (read once from the TM worker's Redis set — no DB query), so a partial-publish
             * crash does not re-queue segments that were already processed.
             */
            $alreadyAnalyzed = $this->_getAlreadyAnalyzedSegments((int)$pid);

            // Pre-fetch metadata cache — values are per (id_job, password) pair,
            // identical across all segments of the same job/language
            $metadataCache = [];
            foreach ($this->segments as $k => $queue_element) {
                $queue_element['pid'] = $pid;
                $queue_element['id_segment'] = $queue_element['id'];
                $queue_element['pretranslate_100'] = $this->actual_project_row['pretranslate_100'];
                $queue_element['tm_keys'] = $this->actual_project_row['tm_keys'];
                $queue_element['id_tms'] = $this->actual_project_row['id_tms'];
                $queue_element['id_mt_engine'] = $this->actual_project_row['id_mt_engine'];
                $queue_element['features'] = $projectFeaturesString;
                $queue_element['only_private'] = $this->actual_project_row['only_private_tm'];
                $queue_element['context_before'] = $this->segments[$k - 1]['segment'] ?? null;
                $queue_element['context_after'] = $this->segments[$k + 1]['segment'] ?? null;

                $jsid = explode("-", $queue_element['jsid']); // 749-49:7acfb82b8168,50:47c70434fe78,51:f3f5551e9c4f
                $passwordMap = explode(",", $jsid[1]);

                /**
                 * remove some unuseful fields
                 */
                unset($queue_element['id']);
                unset($queue_element['jsid']);

                try {
                    //store the payable_rates array
                    $jobs_payable_rates = $queue_element['payable_rates'];

                    $languages_job = explode(",", $queue_element['target']);  //now target holds more than one language ex: ( 80415:fr-FR,80416:it-IT )
                    //in memory replacement avoid duplication of the segment list
                    //send in queue every element * number of languages
                    foreach ($languages_job as $index => $_language) {
                        [$id_job, $language] = explode(":", $_language);
                        [, $password] = explode(":", $passwordMap[$index]);

                        $queue_element['password'] = $password;
                        $queue_element['target'] = $language;
                        $queue_element['id_job'] = $id_job;
                        $queue_element['payable_rates'] = $jobs_payable_rates[$id_job]; // assign the right payable rate for the current job

                        $cacheKey = "$id_job:$password";
                        if (!isset($metadataCache[$cacheKey])) {
                            $jobsMetadataDao = new MetadataDao($this->db());
                            $metadataCache[$cacheKey] = [
                                'tm_prioritization' => $jobsMetadataDao->get((int)$id_job, $password, JobsMetadataMarshaller::TM_PRIORITIZATION->value, 10 * 60),
                                'dialect_strict' => $jobsMetadataDao->get((int)$id_job, $password, JobsMetadataMarshaller::DIALECT_STRICT->value, 10 * 60),
                                'public_tm_penalty' => $jobsMetadataDao->get((int)$id_job, $password, JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, 10 * 60),
                            ];
                        }
                        $tm_prioritization = $metadataCache[$cacheKey]['tm_prioritization'];
                        $dialect_strict = $metadataCache[$cacheKey]['dialect_strict'];
                        $public_tm_penalty = $metadataCache[$cacheKey]['public_tm_penalty'];

                        if (!empty($public_tm_penalty)) {
                            $queue_element['public_tm_penalty'] = (int)$public_tm_penalty->value;
                        }

                        if ($tm_prioritization !== null) {
                            $queue_element['tm_prioritization'] = $tm_prioritization->value == 1;
                        }

                        if ($mt_evaluation) {
                            $queue_element['mt_evaluation'] = $mt_evaluation;
                        }

                        if ($dialect_strict) {
                            $queue_element['dialect_strict'] = $dialect_strict->value == 1;
                        }

                        $queue_element['mt_qe_workflow_enabled'] = $mt_qe_workflow_enabled ?? false;
                        if ($mt_qe_workflow_enabled) {
                            $queue_element['mt_qe_workflow_parameters'] = $mt_qe_workflow_parameters;
                        }
                        $queue_element['mt_quality_value_in_editor'] = $mt_quality_value_in_editor ?? false;

                        $queue_element[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value] = $subfiltering_handlers;
                        $queue_element[ProjectsMetadataMarshaller::ICU_ENABLED->value] = $icu_enabled;

                        // Idempotent publish: skip a (segment, job) the TM workers already
                        // analyzed on a previous crashed attempt so it is not re-queued
                        // (empty on the first attempt → publish everything).
                        if (isset($alreadyAnalyzed[(int)$queue_element['id_segment'] . ':' . (int)$id_job])) {
                            continue;
                        }

                        $element = new QueueElement();
                        $element->params = new Params($queue_element);
                        $element->classLoad = TMAnalysisWorker::class;

                        $this->requireQueueHandler()->publishToQueues($queueInfo->queue_name, new Message($element, ['persistent' => $this->requireQueueHandler()->persistent]));

                        if ($k % 100 == 0 || ($k + 1) == count($this->segments)) {
                            $this->logger->debug("AMQ Set Executed " . ($k + 1) . " Language: $language");
                        }
                    }
                } catch (Exception $e) {
                    $this->logger->debug($e->getMessage() . " " . $e->getTraceAsString());
                    throw $e;
                }
            }

            $this->logger->debug('Done in ' . (microtime(true) - $time_start) . " seconds.");
        }

        return $project_creation_success;
    }

    /**
     * @param array<string, mixed> $segmentArray
     * @param array<string, int|float> $equivalentWordMapping
     *
     * @return array{float|int, float|int, string}
     */
    protected function _getWordCountForSegment(array $segmentArray, array $equivalentWordMapping): array
    {
        switch ($segmentArray['match_type']) {
            case '75%-84%':
            case '85%-94%':
            case '95%-99%':
                $eq_word = ($segmentArray['wc'] * $equivalentWordMapping['INTERNAL'] / 100);
                $match_type = 'INTERNAL';
                break;
            case(array_key_exists($segmentArray['match_type'], $equivalentWordMapping)):
                $eq_word = ($segmentArray['wc'] * $equivalentWordMapping[$segmentArray['match_type']] / 100);
                $match_type = $segmentArray['match_type'];
                break;
            default:
                $eq_word = $segmentArray['wc'];
                $match_type = "NO_MATCH";
                break;
        }

        //Set the industry word count equals to the equivalent word count, here we have no machine translation.
        // - The word count for Industry is by definition equal to the word count for Equivalent, except for machine translation (next phase).
        $standard_words = $eq_word;

        return [$eq_word, $standard_words, $match_type];
    }

    /**
     * @param int $pid
     *
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    protected function _getSegmentsForFastVolumeAnalysis(int $pid): array
    {
        //Fallback used only when the serialized .ser payload is missing: it must reconstruct
        //the SAME set ProjectManager writes to the .ser (see writeFastAnalysisData /
        //SegmentStorageService::cleanSegmentsMetadata), i.e. every segment shown in cattool.
        //It must NOT drop locked/ICE segments: the .ser includes segments later marked
        //ICE/locked by pre-translation, and the completion gate
        //(ProjectCompletionRepository::getProjectSegmentsTranslationSummary) counts all
        //show_in_cattool=1 rows, so excluding them here would desync the two paths and could
        //strand a project.

        $query = <<<HD
            SELECT concat( s.id, '-', group_concat( distinct concat( j.id, ':' , j.password ) ) ) AS jsid, s.segment, 
                j.source, s.segment_hash, 
                s.id as id,
                s.raw_word_count,
                GROUP_CONCAT( DISTINCT CONCAT( j.id, ':' , j.target ) ) AS target,
                CONCAT( '{', GROUP_CONCAT( DISTINCT CONCAT( '"', j.id, '"', ':' , j.payable_rates ) SEPARATOR ',' ), '}' ) AS payable_rates
            FROM segments AS s
            INNER JOIN files_job AS fj ON fj.id_file = s.id_file
            INNER JOIN jobs as j ON fj.id_job = j.id
                WHERE j.id_project = ?
                AND s.show_in_cattool = 1
            GROUP BY s.id
            ORDER BY s.id
HD;

        $db = $this->db();
        try {
            $stmt = $db->getConnection()->prepare($query);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute([$pid]);
            $results = $stmt->fetchAll();
        } catch (PDOException $e) {
            LoggerFactory::doJsonLog($e->getMessage());
            throw $e;
        }

        return array_map(function ($segment) {
            $segment['payable_rates'] = array_map(function ($rowPayable) {
                return json_encode($rowPayable);
            }, json_decode($segment['payable_rates'], true));

            return $segment;
        }, $results);
    }

    /**
     * How many segments are in queue before this?
     *
     * @param array{total?: int|null, pid?: int|null, queueInfo?: Context|null} $config
     * @throws Exception
     */
    protected function _setTotal(
        array $config = [
            'total' => null,
            'pid' => null,
            'queueInfo' => null
        ]
    ): void
    {
        if (empty($config['pid'])) {
            throw new Exception('Can Not set a Total without a Queue ID.');
        }

        if (!empty($config['total'])) {
            $_total = $config['total'];
        } else {
            if (empty($config['queueInfo'])) {
                throw new Exception('Need a queue name to get it\'s total or you must provide one');
            }

            $_total = $this->requireQueueHandler()->getQueueLength($config['queueInfo']->queue_name);
        }

        $this->requireQueueHandler()->getRedisClient()->setex(RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $config['pid'], 60 * 60 * 24 /* 24 hours TTL */, $_total);

        $queueInfo = $config['queueInfo'] ?? null;
        if ($queueInfo === null) {
            throw new Exception('Need a queueInfo to track queue position');
        }

        $this->requireQueueHandler()->getRedisClient()->rpush($queueInfo->redis_key, [$config['pid']]);
    }

    /**
     * Select the right Queue (and the associated redis Key) by its length (simplest implementation simple)
     *
     * @param $queueLen     int
     * @param $id_mt_engine int
     *
     * @return Context|null
     *
     * @throws LogInvalidArgumentException
     */
    protected function _getQueueAddressesByPriority(int $queueLen, int $id_mt_engine): ?Context
    {
        $mtEngine = null;
        try {
            $mtEngine = EnginesFactory::getInstance($id_mt_engine, $this->db(), AbstractEngine::class);
        } catch (Exception $e) {
            $this->logger->debug("Caught Exception: " . $e->getMessage());
        }

        //anyway, take the defaults
        $contextList = $this->_queueContextList->list;

        //use this kind of construct to easily add/remove queues and to disable feature by: comment rows or change the switch flag to false
        return match (true) {
            $mtEngine instanceof MMT || $mtEngine instanceof Lara => $contextList['P4'],
            !$mtEngine instanceof MyMemory && !$mtEngine instanceof NONE => $contextList['P3'],
            $queueLen >= 10000 => $contextList['P2'],
            default => $contextList['P1'],
        };
    }

    /**
     * @param int $limit
     *
     * @return array<int, array<string, mixed>>
     * @throws Throwable
     */
    protected function _getLockProjectForVolumeAnalysis(int $limit = 1): array
    {
        $bindParams = ['project_status' => ProjectStatus::STATUS_NEW];

        $and_InstanceId = null;
        if (AppConfig::$INSTANCE_ID !== 0) {
            $and_InstanceId = ' AND instance_id = :instance_id ';
            $bindParams['instance_id'] = AppConfig::$INSTANCE_ID;
        }

        $query = "
        SELECT p.id, id_tms, id_mt_engine, tm_keys, p.pretranslate_100, GROUP_CONCAT( DISTINCT j.id ) AS jid_list, j.only_private_tm, p.id_customer
            FROM projects p
            INNER JOIN jobs j ON j.id_project=p.id
            WHERE status_analysis = :project_status $and_InstanceId
            GROUP BY p.id
        ORDER BY id LIMIT " . $limit;

        $db = $this->db();
        // Needed to address the query to the master database if exists
        $results = $db->transaction(function () use ($db, $query, $bindParams) {
            $stmt = $db->getConnection()->prepare($query);
            $stmt->execute($bindParams);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });

        foreach ($results as $position => $project) {
            // R1: acquire the lock atomically — SET key 1 NX EX 86400. A split setnx + expire could
            // crash between the two calls, leaving _fPid:$pid with no TTL → the project locked until
            // a manual Redis delete. SET … NX returns null when the key already exists.
            $acquired = $this->requireQueueHandler()->getRedisClient()->set('_fPid:' . $project['id'], 1, 'EX', 60 * 60 * 24, 'NX');
            if (!$acquired) {
                unset($results[$position]);
            } else {
                try {
                    $this->_updateProject((int)$project['id'], ProjectStatus::STATUS_BUSY);
                } catch (Throwable $e) {
                    // The BUSY write failed: release the processing lock AND drop the project
                    // from this batch. Leaving it in $results would hand main() a project that is
                    // still STATUS_NEW and no longer lock-protected — this process would analyze
                    // it lockless while another daemon could re-pick it (double analysis / TM
                    // counter pollution). Catch Throwable, not just Exception: a TypeError/Error
                    // from the DAO path must still release the lock, never strand it for the 24h TTL.
                    $this->logger->debug("*** Project {$project['id']}: BUSY update failed ({$e->getMessage()}). Releasing lock, skipping this cycle.");
                    $this->requireQueueHandler()->getRedisClient()->del('_fPid:' . $project['id']);
                    unset($results[$position]);
                }
            }
        }

        return $results;
    }

}
