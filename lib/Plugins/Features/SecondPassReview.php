<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use Model\FeaturesBase\FeatureCodes;

class SecondPassReview extends AbstractRevisionFeature {
    const FEATURE_CODE = 'second_pass_review';

    protected static $dependencies = [
            FeatureCodes::REVIEW_EXTENDED
    ];

}