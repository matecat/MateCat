<?php

namespace FiltersXliffConfig\Xliff\DTO;

use Constants\XliffTranslationStatus;

class Xliff12Rule extends AbstractXliffRule {
    /**
     * @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html
     */
    protected static $_STATES           = XliffTranslationStatus::STATES_12;
    protected static $_STATE_QUALIFIERS = XliffTranslationStatus::STATE_QUALIFIER_12;

}