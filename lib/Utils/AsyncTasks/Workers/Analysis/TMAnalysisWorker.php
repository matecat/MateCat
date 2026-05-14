<?php

namespace Utils\AsyncTasks\Workers\Analysis;

use Exception;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Predis\Connection\ConnectionException as PredisConnectionException;
use Predis\Response\ServerException as PredisServerException;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\AnalysisRedisServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\ProjectCompletionServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\SegmentUpdaterServiceInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\AnalysisRedisService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\DefaultEngineResolver;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\EngineService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\MatchProcessorService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionRepository;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\ProjectCompletionService;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\SegmentUpdaterService;
use Utils\AsyncTasks\Workers\Service\MatchSorter;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\NotSupportedMTException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;

class TMAnalysisWorker extends AbstractWorker
{
    const int ERR_EMPTY_WORD_COUNT = 4;
    const int ERR_WRONG_PROJECT = 5;

    protected FeatureSet $featureSet;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $_matches = null;

    private AnalysisRedisServiceInterface $redisService;
    private SegmentUpdaterServiceInterface $segmentUpdater;
    private ProjectCompletionServiceInterface $projectCompletion;
    private EngineServiceInterface $engineService;
    private MatchProcessorServiceInterface $matchProcessor;

    /**
     * @throws ReflectionException
     */
    public function __construct(
        AMQHandler $queueHandler,
        ?AnalysisRedisServiceInterface $redisService = null,
        ?SegmentUpdaterServiceInterface $segmentUpdater = null,
        ?ProjectCompletionServiceInterface $projectCompletion = null,
        ?EngineServiceInterface $engineService = null,
        ?MatchProcessorServiceInterface $matchProcessor = null,
    ) {
        parent::__construct($queueHandler);

        $this->redisService = $redisService ?? new AnalysisRedisService($queueHandler);
        $this->segmentUpdater = $segmentUpdater ?? new SegmentUpdaterService(Database::obtain());
        $this->projectCompletion = $projectCompletion ?? new ProjectCompletionService($this->redisService, new ProjectCompletionRepository());
        $this->engineService = $engineService ?? new EngineService(new DefaultEngineResolver());
        $this->matchProcessor = $matchProcessor ?? new MatchProcessorService(new MatchSorter());
    }

    /**
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws EmptyElementException
     * @throws Exception
     */
    public function process(AbstractElement $queueElement): void
    {
        $this->_checkDatabaseConnection();
        assert($queueElement instanceof QueueElement);

        $params = $queueElement->params;

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromString($params->features);

        $this->_matches = null;

        $this->_doLog("--- (Worker $this->_workerPid) : Segment $params->id_segment - Job $params->id_job found ");

        $this->_checkForReQueueEnd($queueElement);

        try {
            $this->initializeTMAnalysis($queueElement);
            $this->_checkWordCount($queueElement);

            $config = $this->buildEngineConfig($queueElement);

            $mtPenalty = $params->mt_quality_value_in_editor ? (int)(100 - $params->mt_quality_value_in_editor) : null;

            try {
                $tmMatches = $this->engineService->getTMMatches($config, $this->featureSet, $mtPenalty);
            } catch (ReQueueException $rEx) {
                $this->_doLog("--- (Worker $this->_workerPid) : RequeueException: {$rEx->getMessage()}");
                throw $rEx;
            } catch (NotSupportedMTException) {
                // Do nothing, skip the frame
                $tmMatches = [];
            }

            $mtResult = $this->engineService->getMTTranslation($config, $this->featureSet, $mtPenalty, $queueElement);
            $matches = $this->matchProcessor->sortMatches($mtResult, $tmMatches);

            if (empty($matches)) {
                $this->_doLog("--- (Worker $this->_workerPid) : No contribution found for this segment.");
                $this->_forceSetSegmentAnalyzed($queueElement);
                throw new EmptyElementException("--- (Worker $this->_workerPid) : No contribution found for this segment.", self::ERR_EMPTY_ELEMENT);
            }

            $this->_matches = $matches;
            $this->_doLog("--- (Worker $this->_workerPid) : Segment $params->id_segment - Job $params->id_job matches retrieved.");

            $bestMatch = $this->getHighestNotMT_OrPickTheFirstOne();

            $payableRates = json_decode($params->payable_rates ?? '{}', true);
            if (!is_array($payableRates)) {
                $payableRates = [];
            }

            $payableRates = array_change_key_case($payableRates, CASE_UPPER);

            [$matchType, $discountRate] = $this->getNewMatchTypeAndEquivalentWordDiscount(
                $bestMatch,
                $queueElement,
                $payableRates
            );

            $eqWords = $discountRate * (float)$params->raw_word_count / 100;
            $standardWords = $eqWords;

            if (in_array($matchType, [
                InternalMatchesConstants::MT,
                InternalMatchesConstants::ICE_MT,
                InternalMatchesConstants::TOP_QUALITY_MT,
                InternalMatchesConstants::HIGHER_QUALITY_MT,
                InternalMatchesConstants::STANDARD_QUALITY_MT,
            ])) {
                //Reset the standard word count to be equal to other cat tools which do not have the MT in analysis
                $standardWords = ($payableRates[InternalMatchesConstants::NO_MATCH] ?? 100) * (float)$params->raw_word_count / 100;
            }

            $icuEnabled = !empty($params->icu_enabled);

            $postProcessed = $this->matchProcessor->postProcessMatch(
                $params->segment,
                $params->source,
                $params->target,
                $bestMatch,
                $this->featureSet,
                $matchType,
                $icuEnabled,
                (int)$params->pid
            );

            $suggestionJson = json_encode($matches);

            $tmData = [];
            $tmData['id_job'] = $params->id_job;
            $tmData['id_segment'] = $params->id_segment;
            $tmData['translation'] = $postProcessed['suggestion'];
            $tmData['suggestion'] = $postProcessed['suggestion'];
            $tmData['suggestions_array'] = $suggestionJson;
            $tmData['match_type'] = strtoupper($matchType);
            $tmData['eq_word_count'] = ($eqWords > $params->raw_word_count) ? $params->raw_word_count : $eqWords;
            $tmData['standard_word_count'] = ($standardWords > $params->raw_word_count) ? $params->raw_word_count : $standardWords;
            $tmData['tm_analysis_status'] = 'DONE';
            $tmData['warning'] = (int)($postProcessed['warning'] ?? 0);
            $tmData['serialized_errors_list'] = $postProcessed['serialized_errors_list'] ?? '';
            $tmData['mt_qe'] = $bestMatch['score'] ?? null;

            $tmData['suggestion_source'] = $bestMatch['created_by'] ?? null;
            if (!empty($tmData['suggestion_source'])) {
                if (!str_contains($tmData['suggestion_source'], InternalMatchesConstants::MT)) {
                    $tmData['suggestion_source'] = InternalMatchesConstants::TM;
                } else {
                    $tmData['suggestion_source'] = InternalMatchesConstants::MT;
                }
            }

            $tmData['suggestion_match'] = $bestMatch['match'] ?? 0;
            $tmData = $this->matchProcessor->determinePreTranslateStatus($tmData, $params);

            try {
                $updateRes = $this->segmentUpdater->setAnalysisValue($tmData);
            } catch (Exception $e) {
                $this->_doLog("**** " . $e->getMessage());
                $this->_doLog("**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tmData['id_segment']}");
                throw new ReQueueException("**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tmData['id_segment']}", self::ERR_REQUEUE);
            }

            if ($updateRes === 0) {
                $this->_doLog("Segment {$tmData['id_segment']}-{$tmData['id_job']} not updated (already DONE/SKIPPED or missing), skipping side-effects.");
                return;
            }

            $this->_doLog("Row found: {$tmData['id_segment']}-{$tmData['id_job']} - UPDATED.");
            $this->_doLog("--- (Worker $this->_workerPid) : Segment $params->id_segment - Job $params->id_job updated.");

            // ── POINT OF NO RETURN ────────────────────────────────────────────────
            // MySQL committed. Redis failures MUST NOT escape to the Executor.
            // If they did, the Executor would requeue the message, but on retry
            // setAnalysisValue() returns 0 (segment already DONE) → early return →
            // counter permanently lost → project never completes.
            // See: KNOWN_CONCURRENCY_ISSUES.md #1
            // ─────────────────────────────────────────────────────────────────────
            $this->applyPostCommitSideEffects(
                (int)$params->pid,
                (string)$params->ppassword,
                $eqWords,
                $standardWords
            );

            $this->_doLog("--- (Worker $this->_workerPid) : Segment $params->id_segment - Job $params->id_job acknowledged.");
        } catch (ReQueueException $e) {
            $this->_doLog("--- (Worker $this->_workerPid) : RequeueException: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * @param QueueElement $queueElement
     * @throws EmptyElementException
     * @throws EndQueueException
     * @throws Exception
     */
    protected function _endQueueCallback(QueueElement $queueElement): void
    {
        $this->_forceSetSegmentAnalyzed($queueElement);
        parent::_endQueueCallback($queueElement);
    }

    /**
     * @throws Exception
     */
    protected function _checkWordCount(QueueElement $queueElement): void
    {
        if ($queueElement->params->raw_word_count == 0) {
            $this->_forceSetSegmentAnalyzed($queueElement);
            $this->_doLog("--- (Worker $this->_workerPid) : empty word count segment. acknowledge and continue.");
            throw new EmptyElementException("--- (Worker $this->_workerPid) : empty segment. acknowledge and continue", self::ERR_EMPTY_WORD_COUNT);
        }
    }

    /**
     * @param QueueElement $queueElement
     * @throws EmptyElementException
     * @throws Exception
     */
    protected function _forceSetSegmentAnalyzed(QueueElement $queueElement): void
    {
        $segmentSet = $this->segmentUpdater->forceSetSegmentAnalyzed(
            (int)$queueElement->params->id_segment,
            (int)$queueElement->params->id_job
        );

        if (!$segmentSet) {
            return;
        }

        // POINT OF NO RETURN — DB committed
        $this->applyPostCommitSideEffects(
            (int)$queueElement->params->pid,
            (string)$queueElement->params->ppassword,
            (float)$queueElement->params->raw_word_count,
            (float)$queueElement->params->raw_word_count
        );
    }

    private function initializeTMAnalysis(QueueElement $queueElement): void
    {
        $params = $queueElement->params;
        $sid = $params->id_segment;
        $jid = $params->id_job;
        $pid = (int)$params->pid;

        if ($this->redisService->acquireInitLock($pid)) {
            $this->doInit($pid);
        } else {
            $ready = $this->redisService->waitForInitialization($pid);
            if (!$ready) {
                // Winner likely crashed — init lock TTL (30s) may have expired. Re-try.
                $this->_doLog("--- (Worker $this->_workerPid) : init timeout for PID $pid, attempting re-init");
                if ($this->redisService->acquireInitLock($pid)) {
                    $this->doInit($pid);
                } else {
                    // Another loser beat us to re-init. Wait again.
                    $this->redisService->waitForInitialization($pid);
                }
            }
            $_projectTotSegments = $this->redisService->getProjectTotalSegments($pid);
            $_analyzed = $this->redisService->getProjectAnalyzedCount($pid);

            $this->_doLog("--- (Worker $this->_workerPid) : found $_projectTotSegments, analyzed $_analyzed segments for PID $pid in Redis");
        }

        $this->_doLog("--- (Worker $this->_workerPid) : fetched data for segment $sid-$jid. Project ID is $pid");
    }

    private function doInit(int $pid): void
    {
        $totalSegmentsData = $this->projectCompletion->getProjectSegmentsTranslationSummary($pid);
        $totalSegments = array_pop($totalSegmentsData);
        assert($totalSegments !== null);

        $this->_doLog($totalSegments);

        $projectSegments = (int)($totalSegments['project_segments'] ?? 0);
        $numAnalyzed = (int)($totalSegments['num_analyzed'] ?? 0);

        $this->redisService->initializeProjectCounters($pid, $projectSegments, $numAnalyzed);

        $this->_doLog("--- (Worker $this->_workerPid) : found $projectSegments segments for PID $pid");
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    protected function buildEngineConfig(QueueElement $queueElement): array
    {
        $params = $queueElement->params;

        $_config = [];
        $_config['pid'] = $params->pid;
        $_config['id_project'] = $params->pid;
        $_config['segment'] = $params->segment;
        $_config['source'] = $params->source;
        $_config['target'] = $params->target;
        $_config['email'] = AppConfig::$MYMEMORY_TM_API_KEY;
        $_config['context_before'] = $params->context_before;
        $_config['context_after'] = $params->context_after;
        $_config['additional_params'] = $params->additional_params ?? null;
        $_config['priority_key'] = $params->tm_prioritization ?? null;
        $_config['job_id'] = $params->id_job ?? null;
        $_config[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value] = isset($params->subfiltering_handlers)
            ? $params->subfiltering_handlers->toArray()
            : null;

        if ($params->dialect_strict ?? false) {
            $_config['dialect_strict'] = $params->dialect_strict;
        }

        if (!empty($params->public_tm_penalty)) {
            $_config['public_tm_penalty'] = $params->public_tm_penalty;
        }

        $penaltyMap = TmKeyManager::getPenaltyMap($params->tm_keys, 'r');
        if (!empty($penaltyMap)) {
            foreach ($penaltyMap as $tmKey) {
                $_config['id_user'][] = $tmKey->key;
            }
            $_config['penalty_key'] = $penaltyMap;
        }

        $_config['num_result'] = 3;
        $_config['id_mt_engine'] = $params->id_mt_engine;
        $_config['id_tms'] = $params->id_tms;
        $_config['mt_qe_workflow_enabled'] = (bool)($params->mt_qe_workflow_enabled ?? false);

        if ($_config['mt_qe_workflow_enabled']) {
            $_config['mt_qe_config'] = new MTQEWorkflowParams(json_decode($params->mt_qe_workflow_parameters ?? '', true) ?? []);
        }

        $mtEngine = EnginesFactory::getInstance((int)$params->id_mt_engine, AbstractEngine::class);
        if ($mtEngine instanceof MyMemory) {
            $_config['get_mt'] = true;
            $_config['id_mt_engine'] = 0;  // Don't call MyMemory as MT separately — TMS call already includes MT via get_mt flag
        } else {
            $_config['get_mt'] = false;
        }

        if (!empty($params->only_private)) {
            $_config['onlyprivate'] = true;
        }

        if (!empty($params->only_private) && empty($_config['id_user']) && !$_config['get_mt']) {
            $_config['id_tms'] = 0;
        }

        return $_config;
    }

    /**
     * @throws Exception
     */
    /**
     * Apply Redis side effects after MySQL has committed a segment as DONE.
     *
     * MUST NOT throw. Once MySQL commits, the Executor must not requeue the
     * message — on retry setAnalysisValue() returns 0 (already DONE) and the
     * worker exits early, permanently losing the Redis counter increment and
     * causing the project to never complete.
     *
     * The three post-commit operations have different criticality levels and are
     * therefore handled separately:
     *
     * 1. incrementAnalyzedCount — CRITICAL. Retried with exponential backoff
     *    (500 ms → 4 000 ms, 5 attempts), destroying and recreating the Redis
     *    connection between retries. If all retries are exhausted the failure is
     *    logged at CRITICAL level and the method returns — the project will rely
     *    on operator intervention or a future DB-authoritative reconciliation.
     *
     * 2. decrementSegmentsToAnalyzeOfWaitingProjects — BEST-EFFORT. Affects
     *    queue ordering only, not project completion correctness. A failure is
     *    logged and swallowed so it never blocks tryCloseProject.
     *
     * 3. tryCloseProject — ALWAYS RUNS after increment succeeds. Has its own
     *    internal catch-all (never throws). Idempotent via completion lock.
     *
     * See: KNOWN_CONCURRENCY_ISSUES.md #1
     *
     * @throws Exception only if $pid is empty (programming error in caller)
     */
    private function applyPostCommitSideEffects(
        int $pid,
        string $projectPassword,
        float $eqWords,
        float $standardWords
    ): void {
        // 1. Retry loop for the critical counter increment
        $maxRetries = 5;
        $delayMs    = 500;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->redisService->incrementAnalyzedCount($pid, 1, $eqWords, $standardWords);

                break;
            } catch (PredisConnectionException|PredisServerException $e) {
                if ($attempt === $maxRetries) {
                    $this->_doLog(
                        "CRITICAL: Redis side-effects permanently lost after $maxRetries retries"
                        . " for PID $pid (eq=$eqWords, st=$standardWords)."
                        . " Project may not auto-complete. Error: " . $e->getMessage()
                    );

                    return;
                }

                $this->_doLog(
                    "WARNING: Redis failure (attempt $attempt/$maxRetries) for PID $pid."
                    . " Reconnecting and retrying in {$delayMs}ms. Error: " . $e->getMessage()
                );

                // Destroy the connection so next command opens a fresh TCP socket
                $this->redisService->reconnect();

                usleep($delayMs * 1000);
                $delayMs *= 2; // 500 → 1000 → 2000 → 4000 ms
            }
        }

        // 2. Best-effort decrement (queue ordering, not correctness)
        try {
            $this->decrementSegmentsToAnalyzeOfWaitingProjects($pid);
        } catch (PredisConnectionException|PredisServerException $e) {
            $this->_doLog(
                "WARNING: decrement waiting segments failed for PID $pid,"
                . " queue ordering may drift. Error: " . $e->getMessage()
            );
        }

        // 3. Always attempt project completion (has internal catch-all, never throws)
        $this->projectCompletion->tryCloseProject(
            $pid,
            $projectPassword,
            $this->_myContext->redis_key,
            $this->featureSet
        );
    }

    /**
     * @throws Exception if $projectId is empty (programming error)
     */
    private function decrementSegmentsToAnalyzeOfWaitingProjects(int $projectId): void
    {
        if (empty($projectId)) {
            throw new Exception('Can Not send without a Queue ID. \Analysis\QueueHandler::setQueueID ', self::ERR_WRONG_PROJECT);
        }

        $workingJobs = $this->redisService->getWorkingProjects($this->_myContext->redis_key);

        $found = false;
        foreach ($workingJobs as $value) {
            if ((int)$value === $projectId) {
                $found = true;
            }
            if ($found) {
                $this->redisService->decrementWaitingSegments($value);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getHighestNotMT_OrPickTheFirstOne(): array
    {
        assert($this->_matches !== null);
        foreach ($this->_matches as $match) {
            if (!$this->matchProcessor->isMtMatch($match) && (int)($match['match'] ?? 0) >= 75) {
                return $match;
            }
        }

        return $this->_matches[0];
    }

    /**
     * Calculate the new score match by the Equivalent word mapping.
     *
     * RATIO: I change the value only if the new match is strictly better
     * (in terms of percent paid per word) than the actual one.
     *
     * @param array<string, mixed> $bestMatch
     * @param array<string, float|int> $equivalentWordMapping
     *
     * @return array{0: string, 1: float|int}
     */
    private function getNewMatchTypeAndEquivalentWordDiscount(
        array $bestMatch,
        QueueElement $queueElement,
        array $equivalentWordMapping,
    ): array {
        $tmMatchType = ($this->matchProcessor->isMtMatch($bestMatch) ? InternalMatchesConstants::MT : (string)($bestMatch['match'] ?? InternalMatchesConstants::MT));
        $fastMatchType = strtoupper($queueElement->params->match_type);
        $fastExactMatchType = $queueElement->params->fast_exact_match_type;

        /* is Public TM */
        $publicTM = empty($bestMatch['memory_key']);
        $isIce = isset($bestMatch[InternalMatchesConstants::TM_ICE]) && $bestMatch[InternalMatchesConstants::TM_ICE];

        // When MTQE is enabled, the NO_MATCH and INTERNAL types are not defined in the payable rates. So fall back to the 100% rate, since it is overwritten by design.
        $fastRatePaid = $equivalentWordMapping[$fastMatchType] ?? 100;

        $tmMatchFuzzyBand = '';
        $tmDiscount = 0;
        $ind = null;

        if (stripos($tmMatchType, InternalMatchesConstants::MT) !== false) {
            $score = $bestMatch['score'] ?? 0;

            $tmMatchFuzzyBand = match (true) {
                // First: ICE if score >= 0.9 (regardless of MTQE flag)
                $score >= 0.9 => InternalMatchesConstants::ICE_MT,

                // If not, and MTQE are disabled: generic MT
                !$queueElement->params->mt_qe_workflow_enabled => InternalMatchesConstants::MT,

                // With MTQE enabled, classify by thresholds
                $score >= 0.8 => InternalMatchesConstants::TOP_QUALITY_MT,
                $score >= 0.5 => InternalMatchesConstants::HIGHER_QUALITY_MT,
                default => InternalMatchesConstants::STANDARD_QUALITY_MT,
            };

            $tmDiscount = $equivalentWordMapping[$tmMatchFuzzyBand] ?? 0;
        } else {
            // Normalize TM match type to integer (e.g., "85%" -> 85)
            $ind = intval($tmMatchType);

            if ($ind == 100) {
                // Exact match (100%)
                if ($isIce) {
                    // In-Context Exact match: use ICE band and related discount
                    $tmMatchFuzzyBand = InternalMatchesConstants::TM_ICE;
                    $tmDiscount = $equivalentWordMapping[$tmMatchFuzzyBand] ?? 0;
                } else {
                    // 100% match: distinguish between Public TM and private TM
                    $tmMatchFuzzyBand = $tempTmMatchFuzzyBand = $publicTM
                        ? InternalMatchesConstants::TM_100_PUBLIC
                        : InternalMatchesConstants::TM_100;

                    // If MT+QE workflow is enabled, remap to the corresponding MT_QE alias
                    if ($queueElement->params->mt_qe_workflow_enabled) {
                        $tempTmMatchFuzzyBand = $publicTM
                            ? InternalMatchesConstants::TM_100_PUBLIC_MT_QE
                            : InternalMatchesConstants::TM_100_MT_QE;
                    }

                    // Pick discount using the (possibly remapped) fuzzy band
                    $tmDiscount = $equivalentWordMapping[$tempTmMatchFuzzyBand] ?? 0;
                }
            } elseif ($ind < 50) {
                $tmMatchFuzzyBand = InternalMatchesConstants::NO_MATCH;
                $tmDiscount = $equivalentWordMapping[InternalMatchesConstants::NO_MATCH] ?? 0;
            } elseif ($ind < 75) {
                $tmMatchFuzzyBand = InternalMatchesConstants::TM_50_74;
                $tmDiscount = $equivalentWordMapping[InternalMatchesConstants::TM_50_74] ?? 0;
            } elseif ($ind <= 84) {
                $tmMatchFuzzyBand = InternalMatchesConstants::TM_75_84;
                $tmDiscount = $equivalentWordMapping[InternalMatchesConstants::TM_75_84] ?? 0;
            } elseif ($ind <= 94) {
                $tmMatchFuzzyBand = InternalMatchesConstants::TM_85_94;
                $tmDiscount = $equivalentWordMapping[InternalMatchesConstants::TM_85_94] ?? 0;
            } elseif ($ind <= 99) {
                $tmMatchFuzzyBand = InternalMatchesConstants::TM_95_99;
                $tmDiscount = $equivalentWordMapping[InternalMatchesConstants::TM_95_99] ?? 0;
            }
        }

        // if MM says is ICE, return ICE
        if ($isIce) {
            return [$tmMatchFuzzyBand, $tmDiscount];
        }

        // if there is a repetition with a 100% match type, return 100%
        if ($ind == 100 && $fastMatchType == InternalMatchesConstants::REPETITIONS) {
            return [$tmMatchFuzzyBand, $tmDiscount];
        }

        // if there is a repetition from Fast, keep it in the REPETITIONS bucket
        if ($fastMatchType == InternalMatchesConstants::REPETITIONS) {
            return [$fastMatchType, $equivalentWordMapping[$fastMatchType] ?? 100];
        }

        // if Fast match type > TM match type, return it
        // otherwise return the TM match type
        if ($fastMatchType === InternalMatchesConstants::INTERNAL && !$queueElement->params->mt_qe_workflow_enabled) {
            $indFast = intval($fastExactMatchType);

            if ($indFast > $ind) {
                return [$fastMatchType, $equivalentWordMapping[$fastMatchType] ?? 100];
            }

            return [$tmMatchFuzzyBand, $tmDiscount];
        }

        /**
         * Apply the TM discount rate and/or force the value obtained from TM for
         * matches between 50%-74% because is never returned in Fast Analysis; it's rate is set default as equals to NO_MATCH
         */
        if (
            in_array($fastMatchType, [InternalMatchesConstants::INTERNAL, InternalMatchesConstants::REPETITIONS])
            && $tmDiscount <= $fastRatePaid
            || $fastMatchType == InternalMatchesConstants::NO_MATCH
        ) {
            return [$tmMatchFuzzyBand, $tmDiscount];
        }

        return [$fastMatchType, $equivalentWordMapping[$fastMatchType] ?? 100];
    }
}
