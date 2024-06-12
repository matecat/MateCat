<?php

namespace FiltersXliffConfig\Xliff\DTO;

use Constants\XliffTranslationStatus;

class Xliff20Rule extends AbstractXliffRule {

    protected static $_STATES = XliffTranslationStatus::STATES_20;

}