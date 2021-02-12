<?php

namespace Ph\Pipeline\Handlers;

use Ph\Helper\PhReplacer;

class DoublePercent extends AbstractPipelineHandler {

    /**
     * @inheritDoc
     */
    public function handle( array $models ) {

        $segment     = $models[ 'segment' ];
        $translation = $models[ 'translation' ];

        // replace all %% present in segment and in translation
        foreach ( $segment->getTags() as $index => $ph ) {
            if ( $this->isAPhToBeReplaced( $ph[ 2 ], '%%', $segment->getLanguage() ) ) {
                PhReplacer::replaceOriginalContentFromBase64Decoded( $segment, $ph[ 0 ], $ph[ 2 ] );
                $models[ 'segment' ] = $segment;
            }
        }

        foreach ( $translation->getTags() as $index => $ph ) {
            if ( $this->isAPhToBeReplaced( $ph[ 2 ], '%%', $translation->getLanguage() ) ) {
                PhReplacer::replaceOriginalContentFromBase64Decoded( $translation, $ph[ 0 ], $ph[ 2 ] );
                $models[ 'translation' ] = $translation;
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