<?php


namespace Features\SecondPassReview\Controller\API;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/05/2019
 * Time: 10:53
 */
class QualityReportController extends \Features\ReviewExtended\Controller\API\QualityReportController {

    /**
     * @throws \Exception
     */
    public function segments_for_ui() {
        $this->segments( true );
    }

}