<?php

namespace SubFiltering\Filters;

use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Filters\Html\HtmlParser;

class DataRefReplace extends AbstractHandler {

    /**
     * @var array
     */
    private $dataRefMap;

    /**
     * DataRefReplace constructor.
     *
     * @param array $dataRefMap
     */
    public function __construct( array $dataRefMap = []) {
        parent::__construct();
        $this->dataRefMap = $dataRefMap;
    }

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {

        // dataRefMap is present only in xliff 2.0 files
        if(empty($this->dataRefMap)){
            return $segment;
        }

        $dataRefReplacer = new DataRefReplacer($this->dataRefMap);

        //
        // ************************************************************
        // NOTES 2021-04-02
        // ************************************************************
        //
        // Added support for <pc> tags.
        // At this point <pc> tags are incapsulated into a Matecat <ph> generic tag as:
        //
        // Link semplice: &lt;ph id="mtc_1" equiv-text="base64:Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDIiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow=="/&gt;La Repubblica&lt;ph id="mtc_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/&gt;.
        //
        $parsed = \Matecat\XliffParser\Utils\HtmlParser::parse($segment);
        foreach ($parsed as $element){
            if ($element->tagname === 'ph' and isset($element->attributes['equiv-text'])){
                $value = base64_decode(str_replace('base64:','', $element->attributes['equiv-text']));
                if ($this->isAnEncodedPcTag($value)) {
                    $segment = str_replace($element->node, $value, $segment);
                }
            }
        }

        return $dataRefReplacer->replace($segment);
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function isAnEncodedPcTag($string) {
        return strpos($string, '&lt;pc') !== false or $string === '&lt;/pc&gt;';
    }
}