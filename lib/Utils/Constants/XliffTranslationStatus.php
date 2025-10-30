<?php

namespace Utils\Constants;

class XliffTranslationStatus {

    const array STATES_12 = [
            self::FINAL_STATE,
            self::NEEDS_ADAPTATION,
            self::NEEDS_L10N,
            self::NEEDS_REVIEW_ADAPTATION,
            self::NEEDS_REVIEW_L10N,
            self::NEEDS_REVIEW_TRANSLATION,
            self::NEEDS_TRANSLATION,
            self::NEW_STATE,
            self::SIGNED_OFF,
            self::TRANSLATED,
    ];

    const array STATE_QUALIFIER_12 = [
            self::EXACT_MATCH,
            self::FUZZY_MATCH,
            self::ID_MATCH,
            self::LEVERAGED_GLOSSARY,
            self::LEVERAGED_INHERITED,
            self::LEVERAGED_MT,
            self::LEVERAGED_REPOSITORY,
            self::LEVERAGED_TM,
            self::MT_SUGGESTION,
            self::REJECTED_GRAMMAR,
            self::REJECTED_INACCURATE,
            self::REJECTED_LENGTH,
            self::REJECTED_SPELLING,
            self::TM_SUGGESTION
    ];

    const array STATES_20 = [
            self::INITIAL,
            self::TRANSLATED,
            self::REVIEWED,
            self::FINAL_STATE
    ];

    const array ALL_STATES = [
            self::FINAL_STATE,
            self::NEEDS_ADAPTATION,
            self::NEEDS_L10N,
            self::NEEDS_REVIEW_ADAPTATION,
            self::NEEDS_REVIEW_L10N,
            self::NEEDS_REVIEW_TRANSLATION,
            self::NEEDS_TRANSLATION,
            self::NEW_STATE,
            self::SIGNED_OFF,
            self::TRANSLATED,
            self::INITIAL,
            self::TRANSLATED,
            self::REVIEWED,
            self::FINAL_STATE
    ];

    // xliff 1.2 state-qualifiers
    // @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html#state-qualifier
    const string EXACT_MATCH          = 'exact-match';
    const string FUZZY_MATCH          = 'fuzzy-match';
    const string ID_MATCH             = 'id-match';
    const string LEVERAGED_GLOSSARY   = 'leveraged-glossary';
    const string LEVERAGED_INHERITED  = 'leveraged-inherited';
    const string LEVERAGED_MT         = 'leveraged-mt';
    const string LEVERAGED_REPOSITORY = 'leveraged-repository';
    const string LEVERAGED_TM         = 'leveraged-tm';
    const string MT_SUGGESTION        = 'mt-suggestion';
    const string REJECTED_GRAMMAR     = 'rejected-grammar';
    const string REJECTED_INACCURATE  = 'rejected-inaccurate';
    const string REJECTED_LENGTH      = 'rejected-length';
    const string REJECTED_SPELLING    = 'rejected-spelling';
    const string TM_SUGGESTION        = 'tm-suggestion';

    // xliff 1.2 states
    const string NEEDS_TRANSLATION        = 'needs-translation';
    const string NEEDS_L10N               = 'needs-l10n';
    const string NEEDS_ADAPTATION         = 'needs-adaptation';
    const string NEEDS_REVIEW_ADAPTATION  = 'needs-review-adaptation';
    const string NEEDS_REVIEW_L10N        = 'needs-review-l10n';
    const string NEEDS_REVIEW_TRANSLATION = 'needs-review-translation';
    const string SIGNED_OFF               = 'signed-off';

    // xliff 2.0/1.2 states
    const string NEW_STATE   = 'new';
    const string TRANSLATED  = 'translated';
    const string FINAL_STATE = 'final';

    // xliff 2.0 states
    const string INITIAL  = 'initial';
    const string REVIEWED = 'reviewed';

    /**
     * Those state-qualifiers (xliff 1.2) must force the translation to status NEW
     *
     * @param $status
     *
     * @return bool
     */
    public static function isStateQualifierNew( $status ): bool {
        return in_array( $status, [
                self::FUZZY_MATCH,
                self::MT_SUGGESTION,
                self::LEVERAGED_INHERITED,
                self::LEVERAGED_TM,
                self::LEVERAGED_MT
        ] );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isStatusNew( $status ): bool {
        return in_array( $status, [
                self::NEW_STATE,
                self::INITIAL, // xliff 2.0
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
    public static function isTranslated( $status ): bool {
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
    public static function isRevision( $status ): bool {
        return self::isR1( $status );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isR1( $status ): bool {
        return in_array( $status, [
                self::REVIEWED, // xliff 2.0
                self::SIGNED_OFF,
        ] );
    }

    /**
     * @param $status
     *
     * @return bool
     */
    public static function isFinalState( $status ): bool {
        return $status === self::FINAL_STATE; // xliff 2.0 / 1.2
    }
}