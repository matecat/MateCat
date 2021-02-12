<?php

namespace Ph;

use Ph\Models\PhAnalysisModel;
use Ph\Pipeline\Handlers\DoublePercent;
use Ph\Pipeline\Handlers\PercentBan;

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
    public function __construct($source, $target, $segment, $translation) {
        $this->pipeline = $this->createPipeline();

        $a = $this->pipeline->execute(new PhAnalysisModel($source, $segment));
        $b = $this->pipeline->execute(new PhAnalysisModel($target, $translation));

        $this->compare($a, $b);
    }

    /**
     * @return Pipeline\Pipeline
     */
    private function createPipeline() {
        $pipeline = new Pipeline\Pipeline();
        $pipeline->add(new DoublePercent());
        $pipeline->add(new PercentBan());

        return $pipeline;
    }

    /**
     * @param PhAnalysisModel $segment
     * @param PhAnalysisModel $target
     */
    private function compare(PhAnalysisModel $segment, PhAnalysisModel $target)
    {
        $this->segment = $segment;
        $this->translation = $target;
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