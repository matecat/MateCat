<?php

namespace Utils\XliffReplacer;

use Exception;
use Matecat\SubFiltering\AbstractFilter;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Utils\DataRefReplacer;
use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Utils\LQA\QA;

class XliffReplacerCallback implements XliffReplacerCallbackInterface {

    /**
     * @var MateCatFilter
     */
    private $filter;


    /**
     * @var string
     */
    private string $sourceLang;

    /**
     * @var string
     */
    private string $targetLang;

    private FeatureSet $featureSet;

    /**
     * XliffReplacerCallback constructor.
     *
     * @param FeatureSet $featureSet
     * @param string     $sourceLang
     * @param string     $targetLang
     *
     * @param int|null   $idProject
     */
    public function __construct( FeatureSet $featureSet, string $sourceLang, string $targetLang, JobStruct $jobStruct = null ) {
        $this->featureSet = $featureSet;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;

        $subfilteringCustomHandlers = [];
        if ( $jobStruct !== null ) {
            $metadataDao                = new MetadataDao();
            $subfilteringCustomHandlers = $metadataDao->getSubfilteringCustomHandlers( $jobStruct->id, $jobStruct->password );
        }

        $this->filter = MateCatFilter::getInstance( $featureSet, $sourceLang, $targetLang, [], $subfilteringCustomHandlers );
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function thereAreErrors( int $segmentId, string $segment, string $translation, ?array $dataRefMap = [], ?string $error = null ): bool {

        // if there are ERR_SIZE_RESTRICTION errors, return true
        if ( $error !== null ) {
            $errors = json_decode( $error );

            if ( $errors ) {
                foreach ( $errors as $err ) {
                    if ( isset( $err->outcome ) and $err->outcome === QA::ERR_SIZE_RESTRICTION ) {
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
        if ( !empty( $dataRefMap ) ) {
            $dataRefReplacer = new DataRefReplacer( $dataRefMap );
            $segment         = $dataRefReplacer->replace( $segment );
            $translation     = $dataRefReplacer->replace( $translation );
        }

        $check = new QA ( $segment, $translation );
        $check->setFeatureSet( $this->featureSet );
        $check->setTargetSegLang( $this->targetLang );
        $check->setSourceSegLang( $this->sourceLang );
        $check->setIdSegment( $segmentId );
        $check->performConsistencyCheck();

        return $check->thereAreErrors();
    }

    /**
     * @param AbstractFilter|MateCatFilter $filter
     *
     * @return XliffReplacerCallback
     */
    public function setFilter( AbstractFilter $filter ): XliffReplacerCallback {
        $this->filter = $filter;

        return $this;
    }
}