<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewExtended\Controller\API;

use API\V2\Validators\ChunkPasswordValidator;
use API\V2\KleinController;
use Chunks_ChunkStruct;
use Projects_ProjectStruct;
use Features\ReviewExtended\Model\ArchivedQualityReportDao;
use Features\ReviewExtended\Model\QualityReportModel ;
use QualityReport\QualityReportSegmentModel;

class QualityReportController extends KleinController
{

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

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

    public function segments() {

        $this->project = $this->chunk->getProject();

        $ref_segment = $this->request->param( 'ref_segment' );
        $where       = $this->request->param( 'where' );
        $step        = $this->request->param( 'step' );
        $filter        = $this->request->param( 'filter' );

        if ( empty( $ref_segment ) ) {
            $ref_segment = 0;
        }

        if ( empty( $where ) ) {
            $where = "after";
        }

        if ( empty( $step ) ) {
            $step = 10;
        }

        $qrSegmentModel = new QualityReportSegmentModel();
        $options        = [ 'filter' => $filter ];
        $segments_id    = $qrSegmentModel->getSegmentsIdForQR( $this->chunk, $step, $ref_segment, $where, $options );
        if ( count( $segments_id ) > 0 ) {
            $segments = $qrSegmentModel->getSegmentsForQR( $segments_id, $this->chunk );


            $this->response->json( [
                    'files' =>$segments
            ] );
        } else {
            $this->response->json( ['files' =>[] ]);
        }

    }

    public function general(){
        $project = $this->chunk->getProject();
        $this->response->json( [
                'project' => $project,
                'job' => $this->chunk,
        ]);
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