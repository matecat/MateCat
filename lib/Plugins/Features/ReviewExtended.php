<?php


namespace Features ;

use API\V2\Json\ProjectUrls;
use Features\ReviewExtended\View\API\JSON\ProjectUrlsDecorator;

class ReviewExtended extends AbstractRevisionFeature {
    const FEATURE_CODE = 'review_extended' ;

    protected static $conflictingDependencies = [
        ReviewImproved::FEATURE_CODE
    ];

    public static function projectUrls( ProjectUrls $formatted ) {
        $projectUrlsDecorator = new ProjectUrlsDecorator( $formatted->getData() );
        return $projectUrlsDecorator;
    }

}