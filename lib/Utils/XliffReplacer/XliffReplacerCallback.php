<?php

namespace XliffReplacer;

use Exception;
use Features;
use FeatureSet;
use LQA\QA;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Utils\DataRefReplacer;
use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;

class XliffReplacerCallback implements XliffReplacerCallbackInterface {

    /**
     * @var MateCatFilter
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
     * @var Features
     */
    private $featureSet;

    /**
     * XliffReplacerCallback constructor.
     *
     * @param FeatureSet $featureSet
     * @param string      $sourceLang
     * @param string      $targetLang
     *
     * @throws Exception
     */
    public function __construct( FeatureSet $featureSet, $sourceLang, $targetLang ) {
        $this->filter     = MateCatFilter::getInstance( $featureSet, $sourceLang, $targetLang );
        $this->featureSet = $featureSet;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function thereAreErrors( $segmentId, $segment, $translation, array $dataRefMap = [], $error = null ) {

        // if there are ERR_SIZE_RESTRICTION errors, return true
        if($error !== null){
            $errors = json_decode($error);

            if($errors){
                foreach ($errors as $err){
                    if(isset($err->outcome) and $err->outcome === QA::ERR_SIZE_RESTRICTION){
                        return true;
                    }
                }
            }
        }

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
        $check->setIdSegment( $segmentId );
        $check->performConsistencyCheck();

        return $check->thereAreErrors();
    }
}