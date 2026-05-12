<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\FeaturesBase\FeatureSet;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineResolverInterface;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\EngineServiceInterface;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Logger\LoggerFactory;
use Utils\TaskRunner\Exceptions\NotSupportedMTException;
use Utils\TaskRunner\Exceptions\ReQueueException;

class EngineService implements EngineServiceInterface
{
    private EngineResolverInterface $engineResolver;

    public function __construct(EngineResolverInterface $engineResolver)
    {
        $this->engineResolver = $engineResolver;
    }

    /**
     * @param array<string, mixed> $config
     * @param FeatureSet $featureSet
     * @param int|null $mtPenalty
     *
     * @return array<int, array<string, mixed>>
     * @throws ReQueueException
     * @throws NotSupportedMTException
     * @throws Exception
     */
    public function getTMMatches(array $config, FeatureSet $featureSet, ?int $mtPenalty): array
    {
        $tmsEngine = $this->engineResolver->getInstance((int)$config['id_tms']);
        $tmsEngine->setFeatureSet($featureSet);

        $engineConfig = $tmsEngine->getConfigStruct();
        $engineConfig = array_merge($engineConfig, $config);

        $tmsEngine->setMTPenalty($mtPenalty);

        /** @var GetMemoryResponse|array<string, mixed>|null $tms_match */
        $tms_match = $tmsEngine->get($engineConfig);

        if (!$tms_match instanceof GetMemoryResponse) {
            if (empty($tms_match) && ($engineConfig['get_mt'] ?? false)) {
                throw new ReQueueException("Error from MyMemory. Empty field received even if MT was requested.", 2);
            }

            if (!is_array($tms_match)) {
                return [];
            }

            if (empty($tms_match)) {
                return [];
            }

            $firstMatch = reset($tms_match);
            if (is_array($firstMatch)) {
                $normalizedMatches = [];
                foreach ($tms_match as $match) {
                    if (is_array($match)) {
                        $normalizedMatches[] = $match;
                    }
                }

                $matches = $normalizedMatches;
            } else {
                $matches = [$tms_match];
            }
        } else {
            if (!empty($tms_match->error)) {
                throw new ReQueueException("Error from Matches. NULL received.", 2);
            }

            if (!$tms_match->mtLangSupported) {
                throw new NotSupportedMTException("Error from Matches. MT not supported.", 3);
            }

            $matches = $tms_match->get_matches_as_array(1);
        }

        $mt_qe_workflow_enabled = (bool)($config['mt_qe_workflow_enabled'] ?? false);
        $mt_qe_config           = $config['mt_qe_config'] ?? null;
        if (!$mt_qe_config instanceof MTQEWorkflowParams) {
            $mt_qe_config = null;
        }

        return $this->__filterTMMatches($matches, $mt_qe_workflow_enabled, $mt_qe_config);
    }

    /**
     * @param array<string, mixed> $config
     * @param FeatureSet $featureSet
     * @param int|null $mtPenalty
     * @param bool $skipAnalysis
     *
     * @return array<string, mixed>
     */
    public function getMTTranslation(array $config, FeatureSet $featureSet, ?int $mtPenalty, bool $skipAnalysis): array
    {
        $mt_result = [];

        try {
            $mtEngine = $this->engineResolver->getInstance((int)$config['id_mt_engine']);
            $mtEngine->setFeatureSet($featureSet);

            $mtEngine->setAnalysis();
            $mtEngine->setSkipAnalysis($skipAnalysis);

            $engineConfig = $mtEngine->getConfigStruct();
            $engineConfig = array_merge($engineConfig, $config);

            $mtEngine->setMTPenalty($mtPenalty);

            $mt_result = $mtEngine->get($engineConfig);

            if ($mt_result instanceof GetMemoryResponse) {
                if ($mt_result->responseStatus >= 400) {
                    return [];
                }
                $mt_result = $mt_result->get_matches_as_array(1);
                $mt_result = $mt_result['matches'][0] ?? [];
            }

            if (isset($mt_result['error']['code'])) {
                return [];
            }
        } catch (Exception $e) {
            LoggerFactory::doJsonLog($e->getMessage());
        }

        return $mt_result;
    }

    /**
     * Filters Translation Memory (TM) matches based on specific criteria defined in the MTQE workflow parameters.
     * The MTQE workflow is designed to show and use as data analysis only TN >= 100.
     *
     * But there are 2 override flags that can be set in the MTQE workflow parameters:
     * - If the "analysis_ignore_100" flag is set, matches with a score <= 100 are ignored unless they are ICE matches. Matches are restricted to ICEs.
     * - If the "analysis_ignore_101" flag is set, all matches are ignored because we want to ignore everything below equal 101. All matches are restricted.
     *
     * @param array<int, array<string, mixed>> $matches An array of TM matches to be filtered.
     * @param bool $mt_qe_workflow_enabled
     * @param MTQEWorkflowParams|null $mt_qe_config
     *
     * @return array<int, array<string, mixed>> The filtered array of TM matches.
     */
     private function __filterTMMatches(array $matches, bool $mt_qe_workflow_enabled, ?MTQEWorkflowParams $mt_qe_config): array
    {
        return array_filter($matches, function ($match) use ($mt_qe_config, $mt_qe_workflow_enabled) {
            if ($mt_qe_workflow_enabled && $mt_qe_config !== null) {
                // Strictest override: ignore everything ≤ 101 — no TM matches survive, MTQE score is the sole signal.
                if ($mt_qe_config->analysis_ignore_101) {
                    return false;
                }

                // Intermediate override: restrict to ICE matches only — drop all non-ICE ≤ 100.
                if ($mt_qe_config->analysis_ignore_100) {
                    if ((int)$match['match'] <= 100 && !$match[InternalMatchesConstants::TM_ICE]) {
                        return false;
                    }
                }

                // Default MTQE behaviour: keep only TM ≥ 100 (100% and ICE).
                if ((int)$match['match'] < 100) {
                    return false;
                }
            }

            return true;
        });
    }
}
