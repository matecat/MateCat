<?php


namespace Features ;

use API\V2\Json\ProjectUrls;
use BasicFeatureStruct;
use Features\ReviewExtended\View\API\JSON\ProjectUrlsDecorator;

class ReviewExtended extends AbstractRevisionFeature {

    const FEATURE_CODE = 'review_extended' ;

    public static function projectUrls( ProjectUrls $formatted ) {
        $projectUrlsDecorator = new ProjectUrlsDecorator( $formatted->getData() );
        return $projectUrlsDecorator;
    }

    /**
     * @param $projectFeatures
     * @param $controller \NewController|\createProjectController
     *
     * @return mixed
     * @throws \Exception
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ) {
        $projectFeatures[ self::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );
        $projectFeatures                       = $controller->getFeatureSet()->filter( 'filterOverrideReviewExtended', $projectFeatures, $controller );
        return $projectFeatures;
    }

}