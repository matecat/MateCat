<?php

class PhAnaliser {

    private $source;
    private $target;
    private $segment;
    private $translation;

    /**
     * PhAnaliser constructor.
     *
     * @param string $source
     * @param string $target
     * @param string $segment
     * @param string $translation
     */
    public function __construct( $source, $target, $segment, $translation) {
        $this->source = $source;
        $this->target = $target;
        $this->segment = $segment;
        $this->translation = $translation;
    }

    public function analyze(){

    }

    /**
     * @return string
     */
    public function getSegment() {
        return $this->segment;
    }

    /**
     * @return string
     */
    public function getTranslation() {
        return $this->translation;
    }
}