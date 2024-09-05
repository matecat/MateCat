<?php

namespace Xliff\DTO;

use Constants\XliffTranslationStatus;

class Xliff12Rule extends AbstractXliffRule
{
    /**
     * @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html
     */
    protected static array $_STATES           = XliffTranslationStatus::STATES_12;
    protected static array $_STATE_QUALIFIERS = XliffTranslationStatus::STATE_QUALIFIER_12;

}