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
use Features\ReviewExtended;
use Features\ReviewImproved;
use Projects_ProjectStruct;
use API\V2\Json\QALocalWarning;
use Features\ReviewImproved\Model\ArchivedQualityReportDao;
use Features\ReviewImproved\Model\QualityReportModel ;
use CatUtils;

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

        $this->featureSet->loadForProject( $this->project );

        $ref_segment = $this->request->param( 'ref_segment' );
        $where       = $this->request->param( 'where' );
        $step        = $this->request->param( 'step' );

        if ( empty( $ref_segment ) ) {
            $ref_segment = 0;
        }

        if ( empty( $where ) ) {
            $where = "after";
        }

        if ( empty( $step ) ) {
            $step = 10;
        }

        $segmentsDao = new \Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQR( $this->chunk->id, $this->chunk->password, $step, $ref_segment, $where );

        $codes = $this->featureSet->getCodes();
        if ( in_array( ReviewExtended::FEATURE_CODE, $codes ) OR in_array( ReviewImproved::FEATURE_CODE, $codes ) ) {
            $issues = ReviewImproved\Model\QualityReportDao::getIssues( $this->chunk );
        } else {
            $reviseDao                  = new \Revise_ReviseDAO();
            $searchReviseStruct         = \Revise_ReviseStruct::getStruct();
            $searchReviseStruct->id_job = $this->chunk->id;
            $issues                     = $reviseDao->read( $searchReviseStruct );
        }

        $commentsDao = new \Comments_CommentDao;
        $comments = $commentsDao->getThreadsByJob($this->chunk->id);




        foreach ( $data as $i => $seg ) {

            $seg->warnings      = $seg->getLocalWarning();
            $seg->pee           = $seg->getPEE();
            $seg->ice_modified  = $seg->isICEModified();
            $seg->secs_per_word = $seg->getSecsPerWord();

            $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit( $seg->time_to_edit );

            $seg->segment = CatUtils::rawxliff2view( $seg->segment );

            $seg->translation = CatUtils::rawxliff2view( $seg->translation );

            foreach ( $issues as $issue ) {
                if ( $issue->id_segment == $seg->sid ) {
                    $seg->issues[] = $issue;
                }
            }

            foreach ($comments as $comment){
                $comment->templateMessage();
                if($comment->id_segment == $seg->sid){
                    $seg->comments[] = $comment;
                }
            }

            $this->result[ 'data' ][] = $seg;
        }

        $this->response->json( $this->result );
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