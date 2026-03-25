<?php

namespace Model\ProjectCreation;

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

                $check = $this->createQA($source, $target);
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
     * Create a new QA instance.
     * Protected so test subclasses can override to inject stubs.
     */
    protected function createQA(string $source, string $target): QA
    {
        return new QA($source, $target);
    }
}
