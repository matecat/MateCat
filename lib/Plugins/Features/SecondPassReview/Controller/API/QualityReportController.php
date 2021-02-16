<?php


namespace Features\SecondPassReview\Controller\API ;

use Features\SecondPassReview\Model\QualityReportModel;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/05/2019
 * Time: 10:53
 */

class QualityReportController extends \Features\ReviewExtended\Controller\API\QualityReportController {

    public function show() {
        $this->model = new QualityReportModel( $this->chunk );
        $this->model->setDateFormat('c');

        $this->response->json( [
                'quality-report' => $this->model->getStructure()
        ] );
    }

    public function segments() {
        return $this->renderSegments(true);
    }

}