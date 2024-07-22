<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use BasicFeatureStruct;
use createProjectController;
use Exception;
use Features;
use NewController;

class SecondPassReview extends BaseFeature {
    const FEATURE_CODE = 'second_pass_review';

    protected static $dependencies = [
            Features::REVIEW_EXTENDED
    ];

    /**
     * @param array $projectFeatures
     * @param $controller NewController|createProjectController
     *
     * @return array
     * @throws Exception
     */
    public function filterCreateProjectFeatures( array $projectFeatures, $controller ): array {
        $projectFeatures[ self::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );
        return $projectFeatures;
    }

}