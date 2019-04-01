<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use Features;
use Klein\Klein;

class SecondPassReview extends BaseFeature {
    const FEATURE_CODE = 'second_pass_review' ;

    protected static $dependencies = [
            Features::REVIEW_EXTENDED
    ];

    public function beginDoAction( $controller ) {
        Features\SecondPassReview\LegacyController::beginDoAction( $controller ) ;
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST',
                'Features\SecondPassReview\Controller\ReviewsController', 'createReview' );
    }

}