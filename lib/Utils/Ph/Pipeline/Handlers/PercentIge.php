<?php

namespace Ph\Pipeline\Handlers;

use Ph\Helper\PhRegex;
use Ph\Helper\PhReplacer;

class PercentIge extends AbstractPipelineHandler {

    /**
     * @inheritDoc
     */
    public function handle( array $models ) {

        $segment = $models['segment'];
        $translation = $models['translation'];

        // replace all '%ige' present in segment or in translation
        if($this->isAllowedLanguage($segment->getLanguage()) or $this->isAllowedLanguage($translation->getLanguage())){
            foreach (PhRegex::extractPercentIge($segment->getAfter()) as $ige){
                $a = str_replace($ige[0], "%ige", $segment->getAfter());
                $segment->setAfter($a);
                $models['segment'] = $segment;
            }

            // replace all '%ige' present in translation
            foreach (PhRegex::extractPercentIge($translation->getAfter()) as $ige){
                $a = str_replace($ige[0], "%ige", $translation->getAfter());
                $translation->setAfter($a);
                $models['translation'] = $translation;
            }
        }

        return $models;
    }

    /**
     * @inheritDoc
     */
    protected function isAllowedLanguage( $language ) {
        $allowed = [
                'de',
                'de-DE',
        ];

        return in_array($language, $allowed);
    }
}