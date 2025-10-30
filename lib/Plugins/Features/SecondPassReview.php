<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Plugins\Features;

use Model\FeaturesBase\FeatureCodes;

class SecondPassReview extends AbstractRevisionFeature {
    const string FEATURE_CODE = 'second_pass_review';

    protected static array $dependencies = [
            FeatureCodes::REVIEW_EXTENDED
    ];

}