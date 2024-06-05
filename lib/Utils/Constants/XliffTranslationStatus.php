<?php

namespace Constants;

class XliffTranslationStatus {

    // xliff 1.2 state-qualifiers
    // @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#state-qualifier
    const EXACT_MATCH              = 'exact-match';
    const FUZZY_MATCH              = 'fuzzy-match';
    const ID_MATCH                 = 'id-match';
    const LEVERAGED_GLOSSARY       = 'leveraged-glossary';
    const LEVERAGED_INHERITED      = 'leveraged-inherited';
    const LEVERAGED_MT             = 'leveraged-mt';
    const LEVERAGED_REPOSITORY     = 'leveraged-repository';
    const LEVERAGED_TM             = 'leveraged-tm';
    const MT_SUGGESTION            = 'mt-suggestion';
    CONST REJECTED_GRAMMAR         = 'rejected-grammar';
    CONST REJECTED_INACCURATE      = 'rejected-inaccurate';
    CONST REJECTED_LENGTH          = 'rejected-length';
    CONST REJECTED_SPELLING        = 'rejected-spelling';
    CONST TM_SUGGESTION            = 'tm-suggestion';

    // xliff 1.2 states
    const NEEDS_TRANSLATION        = 'needs-translation';
    const NEEDS_L10N               = 'needs-l10n';
    const NEEDS_ADAPTATION         = 'needs-adaptation';
    const NEEDS_REVIEW_ADAPTATION  = 'needs-review-adaptation';
    const NEEDS_REVIEW_L10N        = 'needs-review-l10n';
    const NEEDS_REVIEW_TRANSLATION = 'needs-review-translation';
    const SIGNED_OFF               = 'signed-off';

    // xliff 2.0/1.2 states
    const NEW_STATE   = 'new';
    const TRANSLATED  = 'translated';
    const FINAL_STATE = 'final';

    // xliff 2.0 states
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
            self::LEVERAGED_INHERITED,
            self::LEVERAGED_TM,
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