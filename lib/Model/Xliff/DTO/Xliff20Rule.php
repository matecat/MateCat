<?php

namespace Model\Xliff\DTO;

use Constants\XliffTranslationStatus;

class Xliff20Rule extends AbstractXliffRule {

    /**
     * @see https://docs.oasis-open.org/xliff/xliff-core/v2.0/xliff-core-v2.0.html
     */
    protected static array $_STATES = XliffTranslationStatus::STATES_20;

}