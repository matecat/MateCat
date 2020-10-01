<?php

namespace XliffReplacer;

use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;
use QA;
use SubFiltering\Filter;

class XliffReplacerCallback implements XliffReplacerCallbackInterface {

    /**
     * @var Filter
     */
    private $filter;

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
     * @param string      $targetLang
     *
     * @throws \Exception
     */
    public function __construct( \FeatureSet $featureSet, $targetLang ) {
        $this->filter     = Filter::getInstance( $featureSet );
        $this->featureSet = $featureSet;
        $this->targetLang = $targetLang;
    }

    /**
     * @inheritDoc
     */
    public function thereAreErrors( $segment, $translation ) {
        $check = new QA ( $this->filter->fromLayer0ToLayer1( $segment ), $this->filter->fromLayer0ToLayer1( $translation ) );
        $check->setFeatureSet( $this->featureSet );
        $check->setTargetSegLang( $this->targetLang );
        $check->performTagCheckOnly();

        return $check->thereAreErrors();
    }
}