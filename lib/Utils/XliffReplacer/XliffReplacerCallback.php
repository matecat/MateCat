<?php

namespace XliffReplacer;

use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;
use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use QA;
use SubFiltering\Filter;
use SubFiltering\Filters\DataRefReplace;

class XliffReplacerCallback implements XliffReplacerCallbackInterface {

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
        $this->featureSet = $featureSet;
        $this->targetLang = $targetLang;
    }

    /**
     * @inheritDoc
     */
    public function thereAreErrors( $segment, $translation ) {
        $check = new QA ( $segment, $translation );
        $check->setFeatureSet( $this->featureSet );
        $check->setTargetSegLang( $this->targetLang );
        $check->performTagCheckOnly();

        return $check->thereAreErrors();
    }
}