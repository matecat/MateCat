<?php

namespace FiltersXliffConfig\Xliff\DTO;

use Constants\XliffTranslationStatus;

class Xliff20Rule extends AbstractXliffRule {
    const ALLOWED_STATES = [
            XliffTranslationStatus::INITIAL,
            XliffTranslationStatus::TRANSLATED,
            XliffTranslationStatus::REVIEWED,
            XliffTranslationStatus::FINAL_STATE
    ];

    const STATES = XliffTranslationStatus::STATES_20;

}