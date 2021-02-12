<?php


namespace Ph\Pipeline\Handlers;


use Ph\Helper\PhReplacer;
use Ph\Models\PhAnalysisModel;
use Ph\Pipeline\Contracts\PipelineHandler;

class PercentBan implements PipelineHandler {

    /**
     * @inheritDoc
     */
    public function handle( PhAnalysisModel $model ) {

        foreach ( $model->getTags() as $index => $ph ) {

            $value  = base64_decode( $ph[ 1 ] );

            if($value === '%-ban' and in_array($model->getLanguage(), $this->allowedLanguages())){
                $model->setAfter(PhReplacer::replaceOriginalContent($model, $ph));
            }
        }

        return $model;
    }

    /**
     * @return array
     */
    private function allowedLanguages()
    {
        return [
                'az',
                'az-AZ',
                'hu',
                'hu-HU'
        ];
    }
}