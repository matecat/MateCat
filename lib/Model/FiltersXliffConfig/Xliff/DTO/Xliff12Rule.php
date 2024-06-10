<?php

namespace FiltersXliffConfig\Xliff\DTO;

use Constants\XliffTranslationStatus;

class Xliff12Rule extends AbstractXliffRule {
    /**
     * @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html
     */
    const ALLOWED_STATES = [
            XliffTranslationStatus::FINAL_STATE,
            XliffTranslationStatus::NEEDS_ADAPTATION,
            XliffTranslationStatus::NEEDS_L10N,
            XliffTranslationStatus::NEEDS_REVIEW_ADAPTATION,
            XliffTranslationStatus::NEEDS_REVIEW_L10N,
            XliffTranslationStatus::NEEDS_REVIEW_TRANSLATION,
            XliffTranslationStatus::NEEDS_TRANSLATION,
            XliffTranslationStatus::NEW_STATE,
            XliffTranslationStatus::SIGNED_OFF,
            XliffTranslationStatus::TRANSLATED,
            XliffTranslationStatus::EXACT_MATCH, // state qualifier
            XliffTranslationStatus::FUZZY_MATCH,
            XliffTranslationStatus::ID_MATCH,
            XliffTranslationStatus::LEVERAGED_GLOSSARY,
            XliffTranslationStatus::LEVERAGED_INHERITED,
            XliffTranslationStatus::LEVERAGED_MT,
            XliffTranslationStatus::LEVERAGED_REPOSITORY,
            XliffTranslationStatus::LEVERAGED_TM,
            XliffTranslationStatus::MT_SUGGESTION,
            XliffTranslationStatus::REJECTED_GRAMMAR,
            XliffTranslationStatus::REJECTED_INACCURATE,
            XliffTranslationStatus::REJECTED_LENGTH,
            XliffTranslationStatus::REJECTED_SPELLING,
            XliffTranslationStatus::TM_SUGGESTION
    ];

    const STATES           = XliffTranslationStatus::STATES_12;
    const STATES_QUALIFIER = XliffTranslationStatus::STATE_QUALIFIER_12;

}