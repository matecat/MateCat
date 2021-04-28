<?php

namespace SubFiltering\Filters;

use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use SubFiltering\Commons\AbstractHandler;

class DataRefRestore extends AbstractHandler {

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

        if(empty($this->dataRefMap)){
            return $this->restoreXliffPcTagsFromMatecatPhTags($segment);
        }

        $dataRefReplacer = new DataRefReplacer($this->dataRefMap);
        $segment = $dataRefReplacer->restore($segment);

        return $this->restoreXliffPcTagsFromMatecatPhTags($segment);
    }

    /**
     * This function restores <pc> tags (encoded as Matecat ph tags) without any dataRef correspondence
     * for the persistence layer (layer 0).
     *
     * Example:
     *
     * Testo <ph id="source1_1" dataType="pcStart" originalData="Jmx0O3BjIGlkPSJzb3VyY2UxIiBkYXRhUmVmU3RhcnQ9InNvdXJjZTEiIGRhdGFSZWZFbmQ9InNvdXJjZTEiJmd0Ow==" dataRef="source1" equiv-text="base64:Xw=="/><ph id="mtc_u_1" equiv-text="base64:Jmx0O3BjIGlkPSIxdSIgdHlwZT0iZm10IiBzdWJUeXBlPSJtOnUiJmd0Ow=="/><ph id="mtc_u_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/><ph id="source1_2" dataType="pcEnd" originalData="Jmx0Oy9wYyZndDs=" dataRef="source1" equiv-text="base64:Xw=="/>
     *
     * is transformed to:
     *
     * Testo <pc id="source1" dataRefStart="source1" dataRefEnd="source1"><pc id="1u" type="fmt" subType="m:u"></pc></pc>
     *
     * @param $segment
     *
     * @return string|
     */
    private function restoreXliffPcTagsFromMatecatPhTags( $segment)
    {
        preg_match_all('/<(ph id="mtc_u_(.*?)" equiv-text="(.*?)")\/>/iu', $segment, $matches);

        if(empty($matches[0])){
            return $segment;
        }

        foreach ($matches[0] as $index => $match){
            $value = base64_decode(str_replace('base64:', '', $matches[3][$index]));
            $value = str_replace(['&lt;','&gt;'], ['<', '>'], $value);
            $segment = str_replace($match, $value, $segment);
        }

        return $segment;
    }
}