<?php

namespace Utils\LQA\QA;

/**
 * Handles symbol consistency validation between source and target segments.
 *
 * This class validates that special symbols appear the same number of times
 * in both source and target segments. Different counts indicate potential
 * translation errors.
 *
 * Symbols checked:
 * - € (Euro sign)
 * - @ (At sign)
 * - &amp; (Ampersand - excluding valid entity references)
 * - £ (Pound sign)
 * - % (Percent sign)
 * - = (Equals sign)
 * - Tab characters
 * - * (Star/asterisk)
 * - $ (Dollar sign) - special handling
 * - # (Hash sign) - special handling
 *
 * @package Utils\LQA\QA
 */
class SymbolChecker
{
    /** @var ErrorManager Error manager for reporting symbol errors */
    protected ErrorManager $errorManager;

    /** @var string Source segment for symbol analysis */
    protected string $sourceSeg;

    /** @var string Target segment for symbol analysis */
    protected string $targetSeg;

    /**
     * Creates a new SymbolChecker instance.
     *
     * @param ErrorManager $errorManager Error manager for reporting errors
     */
    public function __construct(ErrorManager $errorManager)
    {
        $this->errorManager = $errorManager;
    }

    /**
     * Sets the source and target segments for analysis.
     *
     * @param string $sourceSeg The source segment
     * @param string $targetSeg The target segment
     * @return void
     */
    public function setSegments(string $sourceSeg, string $targetSeg): void
    {
        $this->sourceSeg = $sourceSeg;
        $this->targetSeg = $targetSeg;
    }

    /**
     * Checks symbol consistency between source and target segments.
     *
     * Counts occurrences of each tracked symbol in both segments and
     * reports an error for each difference in count.
     *
     * @return void
     */
    public function checkSymbolConsistency(): void
    {
        $symbols = [
            '€',
            '@',
            '&amp;',
            '£',
            '%',
            '=',
            ContentPreprocessor::getTabPlaceholder(),
            '\\*'
        ];

        $specialSymbols = ['$', '#'];

        $sourceSeg = $this->sourceSeg;
        $targetSeg = $this->targetSeg;

        foreach ($symbols as $sym) {
            if ($sym === '&amp;') {
                $sourceSeg = str_replace('&amp;amp;', '&amp;', $sourceSeg);
                $targetSeg = str_replace('&amp;amp;', '&amp;', $targetSeg);
                $regex = '/&amp;(?!(#[1-9]\d{1,3}|[A-Za-z][0-9A-Za-z]+);)/iu';
            } else {
                $regex = '/' . $sym . '/iu';
            }

            preg_match_all($regex, strip_tags($sourceSeg), $symbolOccurrencesInSource);
            preg_match_all($regex, strip_tags($targetSeg), $symbolOccurrencesInTarget);

            $symbolOccurrencesInSourceCount = count($symbolOccurrencesInSource[0]);
            $symbolOccurrencesInTargetCount = count($symbolOccurrencesInTarget[0]);

            for ($i = 0; $i < abs($symbolOccurrencesInSourceCount - $symbolOccurrencesInTargetCount); $i++) {
                $this->addSymbolError($sym);
            }
        }

        // Remove placeholders and symbols from source and target and search for special symbols mismatch
        $cleaned_source = str_replace($symbols, "", $sourceSeg);
        $cleaned_target = str_replace($symbols, "", $targetSeg);

        $cleaned_source = preg_replace('/##\$_..\$##/', "", $cleaned_source);
        $cleaned_target = preg_replace('/##\$_..\$##/', "", $cleaned_target);

        foreach ($specialSymbols as $sym) {
            $symbolOccurrencesInSource = mb_substr_count($cleaned_source, $sym);
            $symbolOccurrencesInTarget = mb_substr_count($cleaned_target, $sym);

            for ($i = 0; $i < abs($symbolOccurrencesInSource - $symbolOccurrencesInTarget); $i++) {
                $this->errorManager->addError(ErrorManager::ERR_SPECIAL_ENTITY_MISMATCH);
            }
        }
    }

    /**
     * Add the appropriate error based on the symbol
     */
    private function addSymbolError(string $sym): void
    {
        $tabPlaceholder = ContentPreprocessor::getTabPlaceholder();

        match ($sym) {
            '€' => $this->errorManager->addError(ErrorManager::ERR_EUROSIGN_MISMATCH),
            '@' => $this->errorManager->addError(ErrorManager::ERR_AT_MISMATCH),
            '&amp;' => $this->errorManager->addError(ErrorManager::ERR_AMPERSAND_MISMATCH),
            '£' => $this->errorManager->addError(ErrorManager::ERR_POUNDSIGN_MISMATCH),
            '%' => $this->errorManager->addError(ErrorManager::ERR_PERCENT_MISMATCH),
            '=' => $this->errorManager->addError(ErrorManager::ERR_EQUALSIGN_MISMATCH),
            $tabPlaceholder => $this->errorManager->addError(ErrorManager::ERR_TAB_MISMATCH),
            '\\*' => $this->errorManager->addError(ErrorManager::ERR_STARSIGN_MISMATCH),
            default => null,
        };
    }
}

