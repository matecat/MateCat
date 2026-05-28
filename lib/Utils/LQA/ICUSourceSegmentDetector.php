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
     *
     * @param MessagePatternValidator $validator ICU validator for the source segment
     * @param bool                    $icuEnabled Whether ICU support is enabled for the current project
     *
     * @return bool True when ICU is enabled and the source has valid complex ICU syntax
     */
    public static function sourceContainsIcu(MessagePatternValidator $validator, bool $icuEnabled): bool
    {
        return $icuEnabled
            && $validator->containsComplexSyntax()
            && $validator->isValidSyntax();
    }
}
