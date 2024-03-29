<?php

namespace Constants;

class XliffTranslationStatus {

    // xliff 1.2
    const FUZZY_MATCH              = 'fuzzy-match';
    const MT_SUGGESTION            = 'mt-suggestion';
    const NEEDS_TRANSLATION        = 'needs-translation';
    const NEEDS_L10N               = 'needs-l10n';
    const NEEDS_ADAPTATION         = 'needs-adaptation';
    const NEEDS_REVIEW_ADAPTATION  = 'needs-review-adaptation';
    const NEEDS_REVIEW_L10N        = 'needs-review-l10n';
    const NEEDS_REVIEW_TRANSLATION = 'needs-review-translation';
    const SIGNED_OFF               = 'signed-off';

    // xliff 2.0 AND 1.2
    const NEW_STATE   = 'new';
    const TRANSLATED  = 'translated';
    const FINAL_STATE = 'final';

    // xliff 2.0
    const INITIAL  = 'initial';
    const REVIEWED = 'reviewed';

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isFuzzyMatch( $status ) {
        return in_array( $status, [
                self::FUZZY_MATCH,
                self::MT_SUGGESTION,
        ] );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isNew( $status ) {
        return in_array( $status, [
                self::NEW_STATE,
                self::INITIAL,
                self::NEEDS_TRANSLATION,
                self::NEEDS_L10N,
                self::NEEDS_ADAPTATION,
        ] );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isTranslated( $status ) {
        return in_array( $status, [
                self::TRANSLATED,
                self::NEEDS_REVIEW_ADAPTATION,
                self::NEEDS_REVIEW_L10N,
                self::NEEDS_REVIEW_TRANSLATION,
        ] );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isRevision( $status ) {
        return self::isR1( $status );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isR1( $status ) {
        return in_array( $status, [
                self::REVIEWED,
                self::SIGNED_OFF,
        ] );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isFinalState( $status ) {
        return $status === self::FINAL_STATE;
    }
}