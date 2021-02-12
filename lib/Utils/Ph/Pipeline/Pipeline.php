<?php

namespace Ph\Pipeline;

use Ph\Models\PhAnalysisModel;
use Ph\Pipeline\Handlers\AbstractPipelineHandler;

class Pipeline {

    /**
     * @var AbstractPipelineHandler[]
     */
    private $handlers = [];

    /**
     * @param AbstractPipelineHandler $element
     */
    public function add( AbstractPipelineHandler $element ) {
        $this->handlers[] = $element;
    }

    /**
     * @param PhAnalysisModel $segment
     * @param PhAnalysisModel $translation
     *
     * @return PhAnalysisModel[]
     */
    public function execute( PhAnalysisModel $segment, PhAnalysisModel $translation ) {

        $models = [
              'segment' => $segment,
              'translation' => $translation,
        ];

        foreach ( $this->handlers as $handler ) {
            if($handler instanceof AbstractPipelineHandler){
                $models = $handler->handle( $models );
            }
        }

        return $models;
    }
}