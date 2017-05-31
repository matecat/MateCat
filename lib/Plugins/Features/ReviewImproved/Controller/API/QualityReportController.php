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
use Features\ReviewImproved\Model\ArchivedQualityReportDao;
use LQA\ChunkReviewDao;
use Features\ReviewImproved\Model\QualityReportModel ;

class QualityReportController extends KleinController
{

    /**
     * @var ChunkPasswordValidator
     */
    protected $validator;

    private $model ;

    public function show() {
        $this->model = new QualityReportModel( $this->validator->getChunk() );
        $this->model->setDateFormat('c');

        $this->response->json( array(
                'quality-report' => $this->model->getStructure()
        ));
    }

    public function versions() {
        $dao = new ArchivedQualityReportDao();
        $versions = $dao->getAllByChunk( $this->validator->getChunk() ) ;
        $response = array();

        foreach( $versions as $version ) {
            $response[] = array(
                    'id' => (int) $version->id,
                    'version_number' => (int) $version->version,
                    'created_at' => \Utils::api_timestamp( $version->create_date ),
                    'quality-report' => json_decode( $version->quality_report )
            ) ;
        }

        $this->response->json( array('versions' => $response ) ) ;

    }

    protected function afterConstruct() {
        $this->validator = new ChunkPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();

    }
}