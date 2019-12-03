<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewExtended\Controller\API;

use API\V2\Json\TranslationIssueComment;
use API\V2\Validators\ChunkPasswordValidator;
use API\V2\KleinController;
use Chunks_ChunkStruct;
use Features\ReviewExtended\ReviewUtils;
use INIT;
use Projects_ProjectStruct;
use Features\ReviewExtended\Model\ArchivedQualityReportDao;
use Features\ReviewExtended\Model\QualityReportModel;
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

    protected $model ;

    public function show() {
        $this->model = new QualityReportModel( $this->chunk );
        $this->model->setDateFormat('c');

        $this->response->json( [
                'quality-report' => $this->model->getStructure()
        ] );
    }

    public function segments() {

        $this->project = $this->chunk->getProject();

        $ref_segment = $this->request->param( 'ref_segment' );
        $where       = $this->request->param( 'where' );
        $step        = $this->request->param( 'step' );
        $filter      = $this->request->param( 'filter' );

        if ( empty( $ref_segment ) ) {
            $ref_segment = 0;
        }

        if ( empty( $where ) ) {
            $where = "after";
        }

        if ( empty( $step ) ) {
            $step = 20;
        }

        $qrSegmentModel = new QualityReportSegmentModel( $this->chunk );
        $options        = [ 'filter' => $filter ];
        $segments_id    = $qrSegmentModel->getSegmentsIdForQR( $step, $ref_segment, $where, $options );
        if ( count( $segments_id ) > 0 ) {
            $segments = $qrSegmentModel->getSegmentsForQR( $segments_id );

            $segments = $this->_formatSegments( $segments ) ;

            $this->response->json( [
                    'files'  => $segments,
                    '_links' => $this->_getPaginationLinks( $segments_id, $step, $filter )
            ] );

        } else {
            $this->response->json( ['files' =>[] ]);
        }

    }

    protected function _getPaginationLinks( array $segments_id, $step, array $filter = null ){

        $url = parse_url( $_SERVER[ 'REQUEST_URI' ] );

        $links = [
                "base" => INIT::$HTTPHOST,
                "self" => $_SERVER[ 'REQUEST_URI' ],
        ];

        $filter_query = http_build_query( [ 'filter' => array_filter( $filter ) ] );
        if( $this->chunk->job_last_segment > end( $segments_id ) ){
            $links[ 'next' ] = $url[ 'path' ] . "?ref_segment=" . end( $segments_id ) . ( $step != 20 ? "&step=" . $step : null ) . ( !empty( $filter_query ) ? "&" . $filter_query : null );
        }

        if(  $this->chunk->job_first_segment < reset( $segments_id ) ){
            $links[ 'prev' ] = $url[ 'path' ] . "?ref_segment=" . ( reset( $segments_id ) - ( $step + 1 ) * 2 ) . ( $step != 20 ? "&step=" . $step : null ) . ( !empty( $filter_query ) ? "&" . $filter_query : null );
        }

        return $links;

    }

    /**
     * Change the response json to remove source_page property and change it to revision number.
     *
     * @param $files
     *
     * @return array
     */
    protected function _formatSegments( $files ) {
        $outputArray = [] ;

        foreach( $files as $k0 => $file ) {

            if ( !isset( $outputArray [ $k0 ] [ 'filename' ] ) ) {
                $outputArray [ $k0 ] [ 'filename' ]  = $file['filename'] ;
            }

            foreach( $file['segments'] as $k1 => $segment ) {
                if ( !empty( $segment->issues ) ) {
                    foreach( $segment->issues  as $k2 => $issue ) {
                        $segment->issues[ $k2 ]['revision_number'] = ReviewUtils::sourcePageToRevisionNumber(
                                $segment->issues[ $k2 ]['source_page']
                        );
                        unset( $segment->issues[ $k2 ]['source_page'] );

                        if ( !empty( $issue->comments ) ) {
                            $renderedIssueComments = [] ;
                            foreach( $issue->comments as $k3 => $comment )  {
                                $renderedIssueComments [] = ( new TranslationIssueComment() )->renderItem((object)$comment) ;
                            }
                            $issue->comments = null ;
                            $issue->comments = $renderedIssueComments ;
                        }
                    }
                }

                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] = $file['segments'] [ $k1 ]->toArray();
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'revision_number' ] = ReviewUtils::sourcePageToRevisionNumber(
                        $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'source_page' ]
                );
                unset( $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'source_page']  );
            }
        }

        return $outputArray ;
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