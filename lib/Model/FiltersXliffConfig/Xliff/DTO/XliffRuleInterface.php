<?php

namespace FiltersXliffConfig\Xliff\DTO;

interface XliffRuleInterface {

    /**
     * @param $type
     *
     * @return string[]
     */
    public function getStates( $type = null );

    /**
     * @return string
     */
    public function asEditorStatus();

    /**
     * @param string|null $source
     * @param string|null $target
     *
     * @return bool
     */
    public function isTranslated( $source = null, $target = null );

    /**
     * @return string
     */
    public function asMatchType();

}