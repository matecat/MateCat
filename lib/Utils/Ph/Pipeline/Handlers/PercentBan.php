<?php

namespace Ph\Pipeline\Handlers;

use Ph\Helper\PhRegex;
use Ph\Helper\PhReplacer;

class PercentBan extends AbstractPipelineHandler {

    /**
     * @inheritDoc
     */
    public function handle( array $models ) {

        $segment     = $models[ 'segment' ];
        $translation = $models[ 'translation' ];

        // replace all '%-ban' present in segment
        foreach ( $segment->getTags() as $index => $ph ) {
            if ( $this->isAPhToBeReplaced( $ph[ 2 ], '%-ban', $segment->getLanguage() ) ) {
                PhReplacer::replaceOriginalContentFromBase64Decoded( $segment, $ph[ 0 ], $ph[ 2 ] );
                $models[ 'segment' ] = $segment;

                // loop all ph tags in translation with '%-ban' value
                foreach ( PhRegex::extractByContent( $translation->getAfter(), $ph[ 2 ] ) as $match ) {
                    PhReplacer::replaceOriginalContentFromBase64Decoded( $translation, $match[ 0 ], $ph[ 2 ] );
                    $models[ 'translation' ] = $translation;
                }
            }
        }

        // replace all '%-ban'  present in translation
        foreach ( $translation->getTags() as $index => $ph ) {
            if ( $this->isAPhToBeReplaced( $ph[ 2 ], '%-ban', $translation->getLanguage() ) ) {
                PhReplacer::replaceOriginalContentFromBase64Decoded( $translation, $ph[ 0 ], $ph[ 2 ] );
                $models[ 'translation' ] = $translation;

                // loop all ph tags in segment with '%-ban' value
                foreach ( PhRegex::extractByContent( $segment->getAfter(), $ph[ 2 ] ) as $match ) {
                    PhReplacer::replaceOriginalContentFromBase64Decoded( $segment, $match[ 0 ], $ph[ 2 ] );
                    $models[ 'segment' ] = $segment;
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
    protected function isAllowedLanguage( $language ) {
        $allowed = [
                'az',
                'az-AZ',
                'hu',
                'hu-HU'
        ];

        return in_array( $language, $allowed );
    }
}