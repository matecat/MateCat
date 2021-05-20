<?php

namespace SubFiltering\Filters;

use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use SubFiltering\Commons\AbstractHandler;

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
    public function __construct( array $dataRefMap = [] ) {
        parent::__construct();
        $this->dataRefMap = $dataRefMap;
    }

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {

        // dataRefMap is present only in xliff 2.0 files
        if ( empty( $this->dataRefMap ) ) {
            return $this->replaceXliffPcTagsToMatecatPhTags($segment);
        }

        $dataRefReplacer = new DataRefReplacer( $this->dataRefMap );
        $segment = $dataRefReplacer->replace( $segment );

        return $this->replaceXliffPcTagsToMatecatPhTags($segment);
    }

    /**
     * This function replace encoded pc tags (from Xliff 2.0) without any dataRef correspondence
     * to regular Matecat <ph> tag for UI presentation
     *
     * Example:
     *
     * Text &lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiIGRhdGFSZWZFbmQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:eA=="/&gt;&lt;pc id="1u" type="fmt" subType="m:u"&gt;link&lt;/pc&gt;&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:eA=="/&gt;.
     *
     * is transformed to:
     *
     * Text &lt;ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiIGRhdGFSZWZFbmQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:eA=="/&gt;&lt;ph id="mtc_u_1" equiv-text="base64:Jmx0O3BjIGlkPSIxdSIgdHlwZT0iZm10IiBzdWJUeXBlPSJtOnUiJmd0Ow=="/&gt;link&lt;ph id="mtc_u_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/&gt;&lt;ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:eA=="/&gt;.
     *
     * @param $segment
     *
     * @return string|string[]
     */
    private function replaceXliffPcTagsToMatecatPhTags($segment) {

        preg_match_all('/&lt;(pc .*?)&gt;/iu', $segment, $openingPcTags);
        preg_match_all('/&lt;(\/pc)&gt;/iu', $segment, $closingPcTags);

        if(count($openingPcTags[0]) === 0)  {
            return $segment;
        }

        $phIndex = 1;

        foreach ($openingPcTags[0] as $openingPcTag){
            $phMatecat = '&lt;ph id="mtc_u_'.$phIndex.'" equiv-text="base64:'.base64_encode($openingPcTag).'"/&gt;';
            $segment = str_replace($openingPcTag, $phMatecat, $segment);
            $phIndex++;
        }

        foreach ($closingPcTags[0] as $closingPcTag){
            $phMatecat = '&lt;ph id="mtc_u_'.$phIndex.'" equiv-text="base64:'.base64_encode($closingPcTag).'"/&gt;';
            $segment = str_replace($closingPcTag, $phMatecat, $segment);
            $phIndex++;
        }

        return $segment;
    }
}