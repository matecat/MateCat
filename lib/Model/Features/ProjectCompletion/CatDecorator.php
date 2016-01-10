<?php

namespace Features\ProjectCompletion ;
use AbstractDecorator ;
use Features ;

class CatDecorator extends AbstractDecorator {

    public function decorate() {
        \Log::doLog('CatDecorator -----------------------------------------');

        $job = $this->controller->getJob();
        $this->template->projectCompletionFeature = $job->isFeatureEnabled( Features::PROJECT_COMPLETION );
    }
}
