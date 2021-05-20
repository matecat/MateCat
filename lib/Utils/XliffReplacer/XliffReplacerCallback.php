<?php

namespace XliffReplacer;

use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;
use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use QA;
use SubFiltering\Filter;
use SubFiltering\Filters\DataRefReplace;

class XliffReplacerCallback implements XliffReplacerCallbackInterface {

    /**
     * @var Filter
     */
    private $filter;


    /**
     * @var string
     */
    private $sourceLang;

    /**
     * @var string
     */
    private $targetLang;

    /**
     * @var \Features
     */
    private $featureSet;

    /**
     * XliffReplacerCallback constructor.
     *
     * @param \FeatureSet $featureSet
     * @param string      $sourceLang
     * @param string      $targetLang
     *
     * @throws \Exception
     */
    public function __construct( \FeatureSet $featureSet, $sourceLang, $targetLang ) {
        $this->filter     = Filter::getInstance( $sourceLang, $targetLang, $featureSet );
        $this->featureSet = $featureSet;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
    }

    /**
     * @inheritDoc
     */
    public function thereAreErrors( $segment, $translation, array $dataRefMap = [] ) {

        $segment     = $this->filter->fromLayer0ToLayer1( $segment );
        $translation = $this->filter->fromLayer0ToLayer1( $translation );

        //
        // ------------------------------------
        // NOTE 2021-01-25
        // ------------------------------------
        //
        // In Matecat there are some special characters mapped in data_ref_map (like &#39; for example)
        // that can be omitted in the target.
        // In this case no |||UNTRANSLATED_CONTENT_START||| should be found in the target
        //
        // To skip these characters QA class needs replaced version of segment and target for _addThisElementToDomMap() function
        //
        if(!empty($dataRefMap)){
            $dataRefReplacer     = new DataRefReplacer( $dataRefMap );
            $segment     = $dataRefReplacer->replace( $segment );
            $translation = $dataRefReplacer->replace( $translation );
        }

        $check = new QA ( $segment, $translation );
        $check->setFeatureSet( $this->featureSet );
        $check->setTargetSegLang( $this->targetLang );
        $check->setSourceSegLang( $this->sourceLang );
        $check->performTagCheckOnly();

        return $check->thereAreErrors();
    }
}