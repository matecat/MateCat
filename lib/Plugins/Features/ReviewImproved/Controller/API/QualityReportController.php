<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewImproved\Controller\API;

use API\V2\JobPasswordValidator;
use API\V2\ProtectedKleinController;
use LQA\ChunkReviewDao;
use Features\ReviewImproved\Model\QualityReportModel ;

class QualityReportController extends ProtectedKleinController
{

    /**
     * @var JobPasswordValidator
     */
    protected $validator;

    private $model ;


    public function show() {

        $chunk = $this->validator->getChunk();

        $this->model = new QualityReportModel( $this->validator->getChunk() );
        $this->model->setDateFormat('c');

        $this->response->json( array(
                'quality-report' => $this->model->getStructure()
        ));
    }

    protected function afterConstruct() {
        $this->validator = new JobPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();

    }
}