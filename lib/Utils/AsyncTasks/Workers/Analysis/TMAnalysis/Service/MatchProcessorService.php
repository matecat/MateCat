<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service;

use Exception;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use PDO;
use PDOException;
use RuntimeException;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Interface\MatchProcessorServiceInterface;
use Utils\Constants\Ices;
use Utils\Constants\TranslationStatus;
use Utils\LQA\ICUSourceSegmentDetector;
use Utils\LQA\PostProcess;
use Utils\Logger\LoggerFactory;

class MatchProcessorService implements MatchProcessorServiceInterface
{

    /**
     * @param array<string, mixed> $match
     */
    public function isMtMatch(array $match): bool
    {
        return stripos($match['created_by'] ?? '', InternalMatchesConstants::MT) !== false;
    }

    /**
     * @param array<string, mixed> $mtResult
     * @param array<int, array<string, mixed>> $tmMatches
     *
     * @return array<int, array<string, mixed>>
     */
    public function sortMatches(array $mtResult, array $tmMatches): array
    {
        if (!empty($mtResult)) {
            $tmMatches[] = $mtResult;
        }

        usort($tmMatches, $this->__compareScoreDesc(...));

        return $tmMatches;
    }

    /**
     * Compares two associative arrays based on their 'match' and 'ICE' values.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function __compareScoreDesc(array $a, array $b): int
    {
        $aIsICE = (bool)($a['ICE'] ?? false);
        $bIsICE = (bool)($b['ICE'] ?? false);

        $aMatch = floatval($a['match']);
        $bMatch = floatval($b['match']);

        if ($aMatch == $bMatch) {
            $conditions = [
                [$aIsICE && !$bIsICE, -1],
                [!$aIsICE && $bIsICE, 1],
                [$this->isMtMatch($a), -1],
                [$this->isMtMatch($b), 1]
            ];

            foreach ($conditions as [$condition, $result]) {
                if ($condition) {
                    return $result;
                }
            }

            return 0;
        }

        return ($aMatch < $bMatch ? 1 : -1);
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
        $sourceContainsIcu = ICUSourceSegmentDetector::sourceContainsIcu($sourceValidator, $icuEnabled);

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
     * @param string $segment source segment text (Layer 0)
     * @param string $source source language code
     * @param string $target target language code
     * @param array<string, mixed> $match best TM match (contains 'translation', 'created_by', 'segment')
     * @param FeatureSet $featureSet project feature set
     *
     * @return array<string, mixed> processed match data with keys: suggestion, warning, serialized_errors_list
     * @throws Exception
     */
    public function postProcessMatch(string $segment, string $source, string $target, array $match, FeatureSet $featureSet): array
    {
        $suggestion = $match['translation'] ?? '';

        $filter = MateCatFilter::getInstance($featureSet, $source, $target, []);

        /**
         * if the first match is MT, perform QA realignment because some MT engines break tags
         * also perform a tag ID check and mismatch validation
         */
        if ($this->isMtMatch($match)) {
            // Layer 1 here
            $check = $this->initPostProcess(
                $match['segment'] ?? $segment,
                $suggestion,
                $source,
                $target,
                false,
                $featureSet
            );
            $check->realignMTSpaces();
        } else {
            // Otherwise, try to perform only the tagCheck
            $check = $this->initPostProcess($segment, $suggestion, $source, $target, false, $featureSet);
            $check->performTagCheckOnly();
        }

        //In case of MT matches this should every time be ok because MT preserve tags, but we perform also the check for Memories.
        $err_json = ($check->thereAreErrors()) ? $check->getErrorsJSON() : '';

        // perform a consistency check as setTranslation does
        //  to add spaces to translation if needed
        $check2 = $this->initPostProcess($segment, $suggestion, $source, $target, false, $featureSet);
        $check2->performConsistencyCheck();

        if (!$check2->thereAreErrors()) {
            $suggestion = $check2->getTrgNormalized();
        } else {
            $suggestion = $check2->getTargetSeg();
        }

        $err_json2 = ($check2->thereAreErrors()) ? $check2->getErrorsJSON() : '';

        $suggestion = $filter->fromLayer1ToLayer0($suggestion);

        return [
            'suggestion'             => $suggestion,
            'warning'                => (int)($check->thereAreErrors() || $check2->thereAreErrors()),
            'serialized_errors_list' => $this->mergeJsonErrors($err_json, $err_json2),
        ];
    }

    /**
     * Init a PostProcess instance.
     * This method forces to set source/target languages and wires ICU detection
     * when ICU support is enabled.
     *
     * @param bool $icuEnabled
     *
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

    /**
     * This function is heavy, use, but only if it is necessary
     *
     * @param int $pid
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function getProjectSegmentsTranslationSummary(int $pid): array
    {
        //TOTAL and eq_word should be equals, BUT
        //tm Analysis can fail on some rows because of external service nature, so use TOTAL field instead of eq_word
        //to set the global word counter in job
        //Ref: jobs.new_words
        $query = "
                SELECT
                    id_job,
                    password,
                    SUM(eq_word_count) AS eq_wc,
                    SUM(standard_word_count) AS st_wc,
                    SUM( IF( COALESCE( eq_word_count, 0 ) = 0, raw_word_count, eq_word_count) ) as TOTAL,
                    COUNT( s.id ) AS project_segments,
                    SUM(IF(st.tm_analysis_status IN ('DONE', 'SKIPPED'), 1, 0)) AS num_analyzed
                FROM segment_translations st
                     JOIN segments s ON s.id = id_segment
                     INNER JOIN jobs j ON j.id=st.id_job
                WHERE j.id_project = :pid
                AND s.show_in_cattool = 1
                GROUP BY id_job WITH ROLLUP
        ";

        try {
            $db = Database::obtain();
            //Needed to address the query to the master database if exists
            $stmt = $db->getConnection()->prepare($query);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute(['pid' => $pid]);
            $results = $stmt->fetchAll();
        } catch (PDOException $e) {
            LoggerFactory::doJsonLog($e->getMessage());

            throw new RuntimeException($e);
        }

        return $results;
    }

}
