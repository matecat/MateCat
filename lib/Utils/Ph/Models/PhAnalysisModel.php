<?php

namespace Ph\Models;

use Ph\Helper\PhExtractor;

class PhAnalysisModel {

    private $language;
    private $before;
    private $after;
    private $tags = [];

    public function __construct($language, $before) {
        $this->language = $language;
        $this->before = $before;
        $this->after = $before;
        $this->tags = PhExtractor::extractToMap($before);
    }

    /**
     * @return mixed
     */
    public function getLanguage() {
        return $this->language;
    }

    /**
     * @return mixed
     */
    public function getBefore() {
        return $this->before;
    }

    /**
     * @return null
     */
    public function getAfter() {
        return $this->after;
    }

    /**
     * @param null $after
     */
    public function setAfter( $after ) {
        $this->after = $after;
    }

    /**
     * @return array
     */
    public function getTags() {
        return $this->tags;
    }

    /**
     * @param array $tags
     */
    public function setTags( $tags ) {
        $this->tags = $tags;
    }
}