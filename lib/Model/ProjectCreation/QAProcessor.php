<?php

namespace Model\ProjectCreation;

use Exception;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Utils\LQA\QA;

/**
 * Runs QA consistency checks on pre-translated segments.
 *
 * Converts source/target from Layer 0 to Layer 1, runs {@see QA::performConsistencyCheck()},
 * selects the appropriate target string, converts back to Layer 0, and writes the results
 * onto each {@see TranslationTuple} in place.
 *
 * When ICU is enabled for the project, the processor also detects ICU MessageFormat
 * patterns in each segment and passes a {@see MessagePatternComparator} to QA so that
 * ICU consistency is validated alongside tag checks.
 *
 * Produces 4 scalars per tuple:
 * - translationLayer0: the QA-processed target (Layer 0)
 * - suggestionLayer0: same as translationLayer0 (may diverge in future)
 * - serializedErrors: JSON string of QA errors, or empty string
 * - warning: 1 if QA found errors, 0 otherwise
 */
class QAProcessor
{
    public function __construct(
        private readonly MateCatFilter $filter,
        private readonly FeatureSet $features,
        private readonly bool $icuEnabled = false,
    ) {
    }

    /**
     * Run QA consistency checks on all translation tuples in the project structure.
     *
     * Mutates each TranslationTuple in place, setting the 4 QA-result properties.
     *
     * @param ProjectStructure $projectStructure contains translations to process
     * @param string $sourceLang source language code (e.g. 'en-US')
     * @param string $targetLang target language code (e.g. 'it-IT')
     *
     * @throws Exception
     */
    public function process(
        ProjectStructure $projectStructure,
        string $sourceLang,
        string $targetLang,
    ): void {
        foreach ($projectStructure->translations as $struct) {
            if (empty($struct)) {
                continue;
            }

            foreach ($struct as $tuple) {
                $source = $this->filter->fromLayer0ToLayer1($tuple->source);
                $target = $this->filter->fromLayer0ToLayer1($tuple->target);

                [$comparator, $sourceContainsIcu] = $this->detectIcu(
                    $sourceLang,
                    $targetLang,
                    $tuple->source,
                    $tuple->target,
                );

                $check = $this->createQA($source, $target, $comparator, $sourceContainsIcu);
                $check->setFeatureSet($this->features);
                $check->setSourceSegLang($sourceLang);
                $check->setTargetSegLang($targetLang);
                $check->performConsistencyCheck();

                if (!$check->thereAreErrors()) {
                    $translation = $check->getTrgNormalized();
                } else {
                    $translation = $check->getTargetSeg();
                }

                $tuple->translationLayer0 = $this->filter->fromLayer1ToLayer0($translation);
                $tuple->suggestionLayer0  = $tuple->translationLayer0;
                $tuple->serializedErrors  = $check->thereAreErrors() ? $check->getErrorsJSON() : '';
                $tuple->warning           = $check->thereAreErrors() ? 1 : 0;
            }
        }
    }

    /**
     * Detect ICU MessageFormat patterns in the source segment.
     *
     * Uses the raw Layer 0 content for detection because curly-bracket filter
     * handlers are disabled by default at project-creation time, so ICU syntax
     * survives the L0→L1 round-trip.
     *
     * @param string $sourceLang source language code
     * @param string $targetLang target language code
     * @param string $rawSource  raw Layer 0 source content
     * @param string $rawTarget  raw Layer 0 target content
     *
     * @return array{0: ?MessagePatternComparator, 1: bool}
     *         [comparator (null when ICU is not detected), sourceContainsIcu flag]
     */
    private function detectIcu(
        string $sourceLang,
        string $targetLang,
        string $rawSource,
        string $rawTarget,
    ): array {
        if (!$this->icuEnabled) {
            return [null, false];
        }

        $sourceValidator = new MessagePatternValidator($sourceLang, $rawSource);

        $sourceContainsIcu = $sourceValidator->containsComplexSyntax()
            && $sourceValidator->isValidSyntax();

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
     * Create a new QA instance.
     * Protected so test subclasses can override to inject stubs.
     *
     * @param string                        $source            Layer 1 source segment
     * @param string                        $target            Layer 1 target segment
     * @param MessagePatternComparator|null $comparator        ICU pattern comparator (null when ICU not detected)
     * @param bool                          $sourceContainsIcu whether the source contains ICU patterns
     */
    protected function createQA(
        string $source,
        string $target,
        ?MessagePatternComparator $comparator = null,
        bool $sourceContainsIcu = false,
    ): QA {
        return new QA($source, $target, $comparator, $sourceContainsIcu);
    }
}
