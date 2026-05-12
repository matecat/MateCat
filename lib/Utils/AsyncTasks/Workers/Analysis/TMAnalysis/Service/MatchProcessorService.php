<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\AsyncTasks\Workers\Interface\MatchSorterInterface;
use Utils\Constants\Ices;
use Utils\Constants\TranslationStatus;
use Utils\LQA\ICUSourceSegmentDetector;
use Utils\LQA\PostProcess;

class MatchProcessorService implements MatchProcessorServiceInterface
{
    private MatchSorterInterface $matchSorter;

    public function __construct(MatchSorterInterface $matchSorter)
    {
        $this->matchSorter = $matchSorter;
    }

    /**
     * @param array<string, mixed> $match
     */
    public function isMtMatch(array $match): bool
    {
        return $this->matchSorter->isMtMatch($match);
    }

    /**
     * @param array<string, mixed>              $mtResult
     * @param array<int, array<string, mixed>>  $tmMatches
     *
     * @return array<int, array<string, mixed>>
     */
    public function sortMatches(array $mtResult, array $tmMatches): array
    {
        return $this->matchSorter->sortMatches($mtResult, $tmMatches);
    }

    /**
     * Detect ICU MessageFormat errors between source and target in the match.
     *
     * @param string $source source language code
     * @param string $target target language code
     * @param array<string, mixed> $match match array containing 'segment' (source) and 'translation' (target)
     *
     * @return array<string, mixed>|null parsed errors array, or null if no ICU errors
     */
    public function detectIcuErrors(string $source, string $target, array $match): ?array
    {
        $rawSource = $match['segment'] ?? '';
        $rawTarget = $match['translation'] ?? '';

        [$comparator, $sourceContainsIcu] = $this->detectIcu(
            $source,
            $target,
            $rawSource,
            $rawTarget,
            true,
        );

        if (!$sourceContainsIcu) {
            return null;
        }

        $check = new PostProcess($rawSource, $rawTarget, $comparator, $sourceContainsIcu);
        $errJson = $check->thereAreErrors() ? $check->getErrorsJSON() : '';

        if ($errJson === '') {
            return null;
        }

        return json_decode($errJson, true);
    }

    /**
     * Detect ICU MessageFormat patterns in the source segment.
     *
     * @param string $sourceLang source language code
     * @param string $targetLang target language code
     * @param string $rawSource source content
     * @param string $rawTarget target content
     * @param bool $icuEnabled whether ICU support is enabled for the current project
     *
     * @return array{0: ?MessagePatternComparator, 1: bool}
     *         [comparator (null when ICU is not detected), sourceContainsIcu flag]
     */
    private function detectIcu(
        string $sourceLang,
        string $targetLang,
        string $rawSource,
        string $rawTarget,
        bool $icuEnabled,
    ): array {
        if (!$icuEnabled) {
            return [null, false];
        }

        $sourceValidator = new MessagePatternValidator($sourceLang, $rawSource);
        $sourceContainsIcu = ICUSourceSegmentDetector::sourceContainsIcu($sourceValidator, true);

        if (!$sourceContainsIcu) {
            return [null, false];
        }

        $targetValidator = new MessagePatternValidator($targetLang, $rawTarget);

        return [
            MessagePatternComparator::fromValidators($sourceValidator, $targetValidator),
            true,
        ];
    }

    /**
     * Post-process a TM match suggestion: tag check, space realignment, consistency check.
     *
     * Faithfully reproduces the develop branch _updateRecord post-processing:
     * - Uses the RESOLVED fuzzy band ($matchType) to decide MT-realignment vs tag-check-only
     * - Passes $icuEnabled to initPostProcess (for ICU pattern comparison)
     * - Uses project-specific subfiltering handlers for the MateCatFilter
     * - The 'warning' field reflects ONLY the consistency check (second PostProcess instance)
     *
     * @param string $segment source segment text (Layer 0)
     * @param string $source source language code
     * @param string $target target language code
     * @param array<string, mixed> $match best TM match (contains 'translation', 'segment', 'created_by')
     * @param FeatureSet $featureSet project feature set
     * @param string $matchType resolved fuzzy band from scoring (e.g. InternalMatchesConstants::MT, TM_100, REPETITIONS)
     * @param bool $icuEnabled whether ICU support is enabled for this project
     * @param int $pid project ID (for subfiltering custom handlers)
     *
     * @return array<string, mixed> processed match data with keys: suggestion, warning, serialized_errors_list
     * @throws Exception
     */
    public function postProcessMatch(
        string $segment,
        string $source,
        string $target,
        array $match,
        FeatureSet $featureSet,
        string $matchType,
        bool $icuEnabled,
        int $pid,
    ): array {
        $suggestion = $match['translation'] ?? '';

        $metadataDao = new ProjectsMetadataDao();
        $filter = MateCatFilter::getInstance(
            $featureSet,
            $source,
            $target,
            [],
            $metadataDao->getProjectStaticSubfilteringCustomHandlers($pid)
        );

        /**
         * if the resolved match type is an MT band, perform QA realignment because some MT engines break tags.
         * Uses the RESOLVED fuzzy band, not raw created_by — matches develop's in_array($fuzzy_band, [...MT bands...])
         */
        if (in_array($matchType, [
            InternalMatchesConstants::MT,
            InternalMatchesConstants::ICE_MT,
            InternalMatchesConstants::TOP_QUALITY_MT,
            InternalMatchesConstants::HIGHER_QUALITY_MT,
            InternalMatchesConstants::STANDARD_QUALITY_MT,
        ])) {
            // Layer 1 here
            $check = $this->initPostProcess(
                $match['segment'] ?? $segment,
                $suggestion,
                $source,
                $target,
                $icuEnabled,
                $featureSet
            );
            $check->realignMTSpaces();
        } else {
            // Otherwise, try to perform only the tagCheck
            $check = $this->initPostProcess($segment, $suggestion, $source, $target, $icuEnabled, $featureSet);
            $check->performTagCheckOnly();
        }

        //In case of MT matches this should every time be ok because MT preserve tags, but we perform also the check for Memories.
        $err_json = ($check->thereAreErrors()) ? $check->getErrorsJSON() : '';

        // perform a consistency check as setTranslation does
        //  to add spaces to translation if needed
        $check = $this->initPostProcess($segment, $suggestion, $source, $target, $icuEnabled, $featureSet);
        $check->performConsistencyCheck();

        if (!$check->thereAreErrors()) {
            $suggestion = $check->getTrgNormalized();
        } else {
            $suggestion = $check->getTargetSeg();
        }

        $err_json2 = ($check->thereAreErrors()) ? $check->getErrorsJSON() : '';

        $suggestion = $filter->fromLayer1ToLayer0($suggestion);

        return [
            'suggestion'             => $suggestion,
            'warning'                => (int)$check->thereAreErrors(), // ONLY consistency check — matches develop
            'serialized_errors_list' => $this->mergeJsonErrors($err_json, $err_json2),
        ];
    }

    /**
     * Init a PostProcess instance.
     * This method forces to set source/target languages and wires ICU detection
     * when ICU support is enabled.
     *
     * @param string $source_seg
     * @param string $target_seg
     * @param string $source_lang
     * @param string $target_lang
     * @param bool $icuEnabled
     * @param FeatureSet|null $featureSet
     * @return PostProcess
     * @throws Exception
     */
    private function initPostProcess(
        string $source_seg,
        string $target_seg,
        string $source_lang,
        string $target_lang,
        bool $icuEnabled = false,
        ?FeatureSet $featureSet = null,
    ): PostProcess {
        [$comparator, $sourceContainsIcu] = $this->detectIcu(
            $source_lang,
            $target_lang,
            $source_seg,
            $target_seg,
            $icuEnabled,
        );

        $check = new PostProcess($source_seg, $target_seg, $comparator, $sourceContainsIcu);
        if ($featureSet !== null) {
            $check->setFeatureSet($featureSet);
        }
        $check->setSourceSegLang($source_lang);
        $check->setTargetSegLang($target_lang);

        return $check;
    }

    /**
     * @param string $err_json
     * @param string $err_json2
     *
     * @return false|string
     */
    private function mergeJsonErrors(string $err_json, string $err_json2): false|string
    {
        if ($err_json === '' and $err_json2 === '') {
            return '';
        }

        if ($err_json2 === '') {
            return $err_json;
        }

        if ($err_json === '') {
            return $err_json2;
        }

        return json_encode(array_merge_recursive(json_decode($err_json, true), json_decode($err_json2, true)));
    }

    /**
     * Calculate the equivalent word count discount for a given fuzzy band.
     *
     * @param string $matchType the resolved fuzzy band (e.g. InternalMatchesConstants::TM_ICE, InternalMatchesConstants::MT)
     * @param float $rawWordCount raw word count for the segment
     * @param array<string, float|int> $payableRates equivalent word mapping (fuzzy band => rate)
     *
     * @return array{0: string, 1: float|int, 2: float|int} [$matchType, $eqWordCount, $standardWordCount]
     */
    public function calculateWordDiscount(string $matchType, float $rawWordCount, array $payableRates): array
    {
        $discountRate = $payableRates[$matchType] ?? 100;
        $eqWordCount  = $discountRate * $rawWordCount / 100;

        //Reset the standard word count to be equal to other cat tools which do not have the MT in analysis
        $standardWordCount = $eqWordCount;
        if (in_array($matchType, [
            InternalMatchesConstants::MT,
            InternalMatchesConstants::ICE_MT,
            InternalMatchesConstants::TOP_QUALITY_MT,
            InternalMatchesConstants::HIGHER_QUALITY_MT,
            InternalMatchesConstants::STANDARD_QUALITY_MT,
        ])) {
            $standardWordCount = ($payableRates[InternalMatchesConstants::NO_MATCH] ?? 100) * $rawWordCount / 100;
        }

        return [$matchType, $eqWordCount, $standardWordCount];
    }

    /**
     * Check match percentage and set locked/status fields for pre-translate and ICE scenarios.
     *
     * @param array<string, mixed> $tmData translation data array
     * @param object $params queue element params (must expose: target, pretranslate_100, mt_qe_workflow_enabled)
     * @phpstan-param object&object{target: string, pretranslate_100: mixed, mt_qe_workflow_enabled: mixed} $params
     *
     * @return array<string, mixed>
     */
    public function determinePreTranslateStatus(array $tmData, object $params): array
    {
        //Separates if branches to make the conditions more readable
        if (stripos($tmData['suggestion_match'], InternalMatchesConstants::TM_100) !== false) {
            if ($tmData['match_type'] == InternalMatchesConstants::TM_ICE) {
                [$lang,] = explode('-', $params->target);

                //I found this language in the list of disabled target languages??
                if (!in_array($lang, Ices::$iceLockDisabledForTargetLangs)) {
                    //ice lock enabled, language not found
                    $tmData['status'] = TranslationStatus::STATUS_APPROVED;
                    $tmData['locked'] = true;
                }
            } elseif ($params->pretranslate_100) {
                $tmData['status'] = TranslationStatus::STATUS_TRANSLATED;
                $tmData['locked'] = false;
            }
        }

        if ($params->mt_qe_workflow_enabled && $tmData['match_type'] == InternalMatchesConstants::ICE_MT) {
            $tmData['status'] = TranslationStatus::STATUS_APPROVED;
            $tmData['locked'] = false;
        }

        return $tmData;
    }
}
