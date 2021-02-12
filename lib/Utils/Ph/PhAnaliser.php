<?php

namespace Ph;

use Ph\Models\PhAnalysisModel;
use Ph\Pipeline\Handlers\DoublePercent;
use Ph\Pipeline\Handlers\PercentBan;
use Ph\Pipeline\Handlers\PercentIge;

class PhAnaliser {

    /**
     * @var PhAnalysisModel
     */
    private $segment;

    /**
     * @var PhAnalysisModel
     */
    private $translation;

    /**
     * @var Pipeline\Pipeline
     */
    private $pipeline;

    /**
     * @param string $source
     * @param string $target
     * @param string $segment
     * @param string $translation
     *
     * PhAnaliser constructor.
     */
    public function __construct( $source, $target, $segment, $translation ) {
        $this->pipeline = $this->createPipeline();

        $segmentModel     = new PhAnalysisModel( $source, $segment );
        $translationModel = new PhAnalysisModel( $target, $translation );

        $models = $this->pipeline->execute( $segmentModel, $translationModel );

        $this->segment     = $models[ 'segment' ];
        $this->translation = $models[ 'translation' ];
    }

    /**
     * @return Pipeline\Pipeline
     */
    private function createPipeline() {
        $pipeline = new Pipeline\Pipeline();
        $pipeline->add( new DoublePercent() );
        $pipeline->add( new PercentBan() );
        $pipeline->add( new PercentIge() );

        return $pipeline;
    }

    /**
     * @return PhAnalysisModel
     */
    public function getSegment() {
        return $this->segment;
    }

    /**
     * @return PhAnalysisModel
     */
    public function getTranslation() {
        return $this->translation;
    }
}