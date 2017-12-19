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
use Chunks_ChunkStruct;
use Features\ReviewImproved\Model\ArchivedQualityReportDao;
use Features\ReviewImproved\Model\QualityReportModel ;

class QualityReportController extends KleinController
{

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    private $model ;

    public function show() {
        $this->model = new QualityReportModel( $this->chunk );
        $this->model->setDateFormat('c');

        $this->response->json( array(
                'quality-report' => $this->model->getStructure()
        ));
    }

    public function versions() {
        $dao = new ArchivedQualityReportDao();
        $versions = $dao->getAllByChunk( $this->chunk ) ;
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
        $Validator = new ChunkPasswordValidator( $this ) ;
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
    }

}