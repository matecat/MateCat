<?php

namespace Ph\Pipeline\Handlers;

use Ph\Helper\PhReplacer;

class DoublePercent extends AbstractPipelineHandler {

    /**
     * @inheritDoc
     */
    public function handle( array $models ) {

        $segment = $models['segment'];
        $translation = $models['translation'];

        // replace all %% present in segment and in translation
        foreach ( $segment->getTags() as $index => $ph ) {
            if($this->isAPhToBeReplaced($ph[ 1 ], '%%', $segment->getLanguage())){
                $segment->setAfter(PhReplacer::replaceOriginalContent($segment, $ph[0], $ph[1]));
                $models['segment'] = $segment;
            }
        }

        foreach ( $translation->getTags() as $index => $ph ) {
            if($this->isAPhToBeReplaced($ph[ 1 ], '%%', $translation->getLanguage())){
                $translation->setAfter(PhReplacer::replaceOriginalContent($translation, $ph[0], $ph[1]));
                $models['translation'] = $translation;
            }
        }

        return $models;
    }

    /**
     * @inheritDoc
     */
    protected function isAllowedLanguage( $language ) {
        return true; // all language allowed
    }
}