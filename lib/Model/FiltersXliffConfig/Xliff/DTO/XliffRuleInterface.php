<?php

namespace FiltersXliffConfig\Xliff\DTO;

interface XliffRuleInterface {
    public function getStates( $type = null );

    /**
     * @return string
     */
    public function getAnalysis();

    /**
     * @return string
     */
    public function getEditor();

}