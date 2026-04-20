<?php

namespace Utils\LQA;

use Matecat\ICU\MessagePatternValidator;

/**
 * Shared ICU source-segment detection logic.
 */
final class ICUSourceSegmentDetector
{
    /**
     * Determines whether a source segment contains valid ICU MessageFormat patterns.
     */
    public static function sourceContainsIcu(MessagePatternValidator $validator, bool $icuEnabled): bool
    {
        return $icuEnabled
            && $validator->containsComplexSyntax()
            && $validator->isValidSyntax();
    }
}
