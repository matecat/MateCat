<?php

namespace XliffReplacer;

use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;
use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use Ph\PhAnaliser;
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
    private $targetLang;

    /**
     * @var \Features
     */
    private $featureSet;

    private $sourceLang;

    /**
     * XliffReplacerCallback constructor.
     *
     * @param \FeatureSet $featureSet
     * @param string      $targetLang
     *
     * @throws \Exception
     */
    public function __construct( \FeatureSet $featureSet, $sourceLang, $targetLang ) {
        $this->filter     = Filter::getInstance( $featureSet );
        $this->featureSet = $featureSet;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
    }

    /**
     * @inheritDoc
     */
    public function thereAreErrors( $segment, $translation, array $dataRefMap = [] ) {

        $phAnaliser = new PhAnaliser(
                $this->sourceLang,
                $this->targetLang,
                $this->filter->fromLayer0ToLayer1( $segment ),
                $this->filter->fromLayer0ToLayer1( $translation )
        );

        $segment     = $phAnaliser->getSegment()->getAfter();
        $translation = $phAnaliser->getTranslation()->getAfter();

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
        $dataRefReplacer     = new DataRefReplacer( $dataRefMap );
        $replacedSegment     = $dataRefReplacer->replace( $segment );
        $replacedTranslation = $dataRefReplacer->replace( $translation );

        $check = new QA ( $replacedSegment, $replacedTranslation );
        $check->setFeatureSet( $this->featureSet );
        $check->setTargetSegLang( $this->targetLang );
        $check->performTagCheckOnly();

        return $check->thereAreErrors();
    }
}