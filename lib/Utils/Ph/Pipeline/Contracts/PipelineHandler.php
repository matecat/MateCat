<?php

namespace Ph\Pipeline\Contracts;

use Ph\Models\PhAnalysisModel;

interface PipelineHandler {

    /**
     * @param PhAnalysisModel $model
     *
     * @return PhAnalysisModel
     */
    public function handle(PhAnalysisModel $model);
}