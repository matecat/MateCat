<?php

namespace Utils\LQA;

use DomainException;
use Matecat\ICU\MessagePatternValidator;

/**
 * Shared ICU source-segment detection logic.
 */
final class ICUSourceSegmentDetector
{
    use ICUSourceSegmentChecker;

    /**
     * Determines whether a source segment contains valid ICU MessageFormat patterns.
     *
     * @param MessagePatternValidator $validator ICU validator for the source segment
     * @param bool                    $icuEnabled Whether ICU support is enabled for the current project
     *
     * @return bool True when ICU is enabled and the source has valid complex ICU syntax
     * @throws DomainException
     */
    public static function sourceContainsIcu(MessagePatternValidator $validator, bool $icuEnabled): bool
    {
        return $icuEnabled
            && $validator->containsComplexSyntax()
            && $validator->isValidSyntax();
    }
}
