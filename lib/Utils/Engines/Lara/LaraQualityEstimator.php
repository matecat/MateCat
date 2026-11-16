<?php

declare(strict_types=1);

namespace Utils\Engines\Lara;

use Exception;
use Lara\QualityEstimationResult;
use Model\DataAccess\IDatabase;
use Psr\Log\LoggerInterface;
use Throwable;
use Utils\Engines\EngineInterface;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\Matches;

/**
 * Scores the first MT match with Lara quality estimation, paid by the engine owner's Lara engine.
 *
 * Example: after $estimator->scoreIfRequested($mtEngine, $response, $engineConfig),
 * $response->matches[0]->score holds the Lara score, such as 0.83.
 */
class LaraQualityEstimator
{
    /**
     * Example: new LaraQualityEstimator($database, LoggerFactory::getLogger('engines'));
     */
    public function __construct(
        private readonly IDatabase $database,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Sets the Lara score on the first match, unless the engine computes its own score.
     * An active Lara engine scores with its own client, the one that just translated; any other
     * engine is scored with its owner's active Lara engine. Without one, or when the lookup or Lara
     * fails, the score stays null and a warning is logged: this method never throws, so a failed
     * score never loses the translation.
     *
     * Example: $estimator->scoreIfRequested($mtEngine, $response, ['include_score' => true, 'source' => 'en-US',
     * 'target' => 'it-IT', 'segment' => 'Hello']);
     *
     * @param array<string, mixed> $engineConfig
     */
    public function scoreIfRequested(EngineInterface $mtEngine, GetMemoryResponse $response, array $engineConfig): void
    {
        $match = $this->matchToScore($mtEngine, $response, $engineConfig);
        if ($match === null) {
            return;
        }

        $result = $this->estimate($mtEngine, $match, $engineConfig);
        if ($result === null) {
            return;
        }

        $match->score = $result->getScore();

        $this->logger->debug('MT quality estimation score added', [
            'score' => $match->score,
            'mt_qe_engine_id' => $engineConfig['mt_qe_engine_id'] ?? null,
        ]);
    }

    /**
     * Returns the match to score, or null when no score is wanted or there is nothing to score.
     *
     * Example: a 500 response, or an engine with its own score such as ModernMT, gives null.
     *
     * @param array<string, mixed> $engineConfig
     */
    private function matchToScore(EngineInterface $mtEngine, GetMemoryResponse $response, array $engineConfig): ?Matches
    {
        $match = $response->matches[0] ?? null;

        if (empty($engineConfig['include_score'])
            || $mtEngine->hasBuiltInQualityEstimation()
            || $response->responseStatus >= 400
            || $match === null
            || $match->raw_translation === ''
        ) {
            return null;
        }

        return $match;
    }

    /**
     * Asks Lara to score the match. Returns null when no Lara engine is found or the call fails (both
     * logged as a warning), and when Lara answers without a single score.
     *
     * Example: for "Hello" translated as "Ciao", Lara can answer a QualityEstimationResult with score 0.83.
     *
     * @param array<string, mixed> $engineConfig
     */
    private function estimate(EngineInterface $mtEngine, Matches $match, array $engineConfig): ?QualityEstimationResult
    {
        $engineRecord = $mtEngine->getEngineRecord();

        try {
            $laraEngine = $mtEngine instanceof Lara && $engineRecord->active
                ? $mtEngine
                : $this->findOwnerLaraEngine($engineRecord->uid);

            if ($laraEngine === null) {
                $this->logger->warning(
                    'MT quality estimation skipped: the engine owner has no active Lara engine',
                    ['uid' => $engineRecord->uid, 'engine_id' => $engineRecord->id]
                );

                return null;
            }

            $result = $laraEngine->getLaraClient()->qualityEstimation(
                $engineConfig['source'] ?? '',
                $engineConfig['target'] ?? '',
                $engineConfig['segment'] ?? '',
                $match->raw_translation
            );

            return $result instanceof QualityEstimationResult ? $result : null;
        } catch (Throwable $exception) {
            // The translation is already paid for: a failed score must never lose it.
            $this->logger->warning(
                'MT quality estimation failed',
                [
                    'uid' => $engineRecord->uid,
                    'engine_id' => $engineRecord->id,
                    'error' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                ]
            );

            return null;
        }
    }

    /**
     * Finds the owner's active Lara engine, built fresh for this run; tests override it.
     *
     * @throws Exception
     */
    protected function findOwnerLaraEngine(?int $uid): ?Lara
    {
        return EnginesFactory::getActiveInstanceByUserAndClass($uid, $this->database, Lara::class);
    }
}
