<?php

namespace Ph\Pipeline\Handlers;

use Ph\Helper\PhRegex;
use Ph\Helper\PhReplacer;

class PercentBan extends AbstractPipelineHandler {

    /**
     * @inheritDoc
     */
    public function handle( array $models ) {

        $segment = $models['segment'];
        $translation = $models['translation'];

        // replace all '%-ban' present in segment
        foreach ( $segment->getTags() as $index => $ph ) {
            if($this->isAPhToBeReplaced($ph[ 1 ], '%-ban', $segment->getLanguage())){
                $segment->setAfter(PhReplacer::replaceOriginalContent($segment, $ph[0], $ph[1]));
                $models['segment'] = $segment;

                // loop all ph tags in translation with '%-ban' value
                foreach (PhRegex::extractByContent($translation->getAfter(), $ph[ 1 ]) as $match){
                    $translation->setAfter(PhReplacer::replaceOriginalContent($translation, $match[0], $ph[1]));
                    $models['translation'] = $translation;
                }
            }
        }

        // replace all '%-ban'  present in translation
        foreach ( $translation->getTags() as $index => $ph ) {
            if($this->isAPhToBeReplaced($ph[ 1 ], '%-ban', $translation->getLanguage())){
                $translation->setAfter(PhReplacer::replaceOriginalContent($translation, $ph[0], $ph[1]));
                $models['translation'] = $translation;

                // loop all ph tags in segment with '%-ban' value
                foreach (PhRegex::extractByContent($segment->getAfter(), $ph[ 1 ]) as $match){
                    $segment->setAfter(PhReplacer::replaceOriginalContent($segment, $match[0], $ph[1]));
                    $models['segment'] = $segment;
                }
            }
        }

        return $models;
    }

    /**
     * @param $language
     *
     * @return bool
     */
    protected function isAllowedLanguage( $language)
    {
        $allowed = [
                'az',
                'az-AZ',
                'hu',
                'hu-HU'
        ];

        return in_array($language, $allowed);
    }
}