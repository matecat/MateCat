<?php

namespace Ph\Pipeline;

use Ph\Models\PhAnalysisModel;
use Ph\Pipeline\Contracts\PipelineHandler;

class Pipeline {

    /**
     * @var PipelineHandler[]
     */
    private $handlers = [];

    /**
     * @param PipelineHandler $element
     */
    public function add( PipelineHandler $element) {
        $this->handlers[] = $element;
    }

    /**
     * @param PhAnalysisModel $model
     *
     * @return PhAnalysisModel
     */
    public function execute(PhAnalysisModel $model){

        foreach ( $this->handlers as $handler){
            $model = $handler->handle($model);
        }

        return $model;
    }
}