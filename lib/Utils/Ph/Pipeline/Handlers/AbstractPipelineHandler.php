<?php

namespace Ph\Pipeline\Handlers;

use Ph\Models\PhAnalysisModel;

abstract class AbstractPipelineHandler {

    /**
     * @param PhAnalysisModel[]
     *
     * @return PhAnalysisModel[]
     */
    abstract public function handle(array $models);

    /**
     * @param string $language
     *
     * @return bool
     */
    abstract protected function isAllowedLanguage($language);

    /**
     * Check if a ph tag should be replaced to its original content
     *
     * @param string $base64Value
     * @param string $search
     * @param string $language
     *
     * @return bool
     */
    protected function isAPhToBeReplaced( $base64Value, $search, $language) {
        return base64_decode($base64Value) === $search and $this->isAllowedLanguage($language);
    }
}