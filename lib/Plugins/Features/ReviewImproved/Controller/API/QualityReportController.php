<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewImproved\Controller\API;

use API\V2\Validators\ChunkPasswordValidator;
use API\V2\KleinController;
use Features\ReviewImproved\Model\QualityReportModel ;

class QualityReportController extends KleinController
{

    /**
     * @var ChunkPasswordValidator
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
        $this->validator = new ChunkPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();

    }
}