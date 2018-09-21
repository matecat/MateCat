<?php


namespace Features ;

use API\V2\Json\ProjectUrls;
use Features\ReviewExtended\View\API\JSON\ProjectUrlsDecorator;
use Features\ReviewExtended\Observer\SegmentTranslationObserver;
use SegmentTranslationModel;

class ReviewExtended extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_extended' ;

    protected static $conflictingDependencies = [
        ReviewImproved::FEATURE_CODE
    ];

    public static function projectUrls( ProjectUrls $formatted ) {
        $projectUrlsDecorator = new ProjectUrlsDecorator( $formatted->getData() );
        return $projectUrlsDecorator;
    }

    protected function attachObserver( SegmentTranslationModel $translation_model ){
        /**
         * This implementation may seem overkill since we are already into review improved feature
         * so we could avoid to delegate to an observer. This is done with aim to the future when
         * the SegmentTranslationModel will be used directly into setTranslation controller.
         */
        $translation_model->attach( new SegmentTranslationObserver() );
    }

}