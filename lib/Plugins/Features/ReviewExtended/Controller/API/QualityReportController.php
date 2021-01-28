<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewExtended\Controller\API;

use API\V2\Json\TranslationIssueComment;
use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Features\ReviewExtended\Model\ArchivedQualityReportDao;
use Features\ReviewExtended\Model\QualityReportModel;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use Files\FilesInfoUtility;
use INIT;
use Projects_ProjectStruct;
use QualityReport\QualityReportSegmentModel;

class QualityReportController extends KleinController {

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

    protected $model;

    public function show() {
        $this->model = new QualityReportModel( $this->chunk );
        $this->model->setDateFormat( 'c' );

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
        $segments_ids   = $qrSegmentModel->getSegmentsIdForQR( $step, $ref_segment, $where, $options );

        if ( count( $segments_ids ) > 0 ) {

            $segmentTranslationEventDao = new SegmentTranslationEventDao();
            $ttlArray                   = $segmentTranslationEventDao->setCacheTTL( 60 * 5 )->getTteForSegments( $segments_ids, $this->chunk->id );
            $segments                   = $qrSegmentModel->getSegmentsForQR( $segments_ids );

            $segments = $this->_formatSegments( $segments, $ttlArray );

            $this->response->json( [
                    'files'  => $segments,
                    '_links' => $this->_getPaginationLinks( $segments_ids, $step, $filter )
            ] );

        } else {
            $this->response->json( [ 'files' => [] ] );
        }

    }

    public function segmentsNew() {

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
        $segments_ids   = $qrSegmentModel->getSegmentsIdForQR( $step, $ref_segment, $where, $options );

        if ( count( $segments_ids ) > 0 ) {

            $segmentTranslationEventDao = new SegmentTranslationEventDao();
            $ttlArray                   = $segmentTranslationEventDao->setCacheTTL( 60 * 5 )->getTteForSegments( $segments_ids, $this->chunk->id );
            $segments                   = $qrSegmentModel->getSegmentsForQR( $segments_ids );

            $filesInfoUtility = new FilesInfoUtility( $this->chunk );
            $filesInfo = $filesInfoUtility->getInfo();

            $segments = $this->_formatSegments( $segments, $ttlArray );

            $this->response->json( [
                    'files'  => $this->mergeFileInfoWithSegments($filesInfo, $segments),
                    'first_segment' => $filesInfo['first_segment'],
                    'last_segment' => $filesInfo['last_segment'],
                    '_links' => $this->_getPaginationLinks( $segments_ids, $step, $filter )
            ] );

        } else {
            $this->response->json( [ 'files' => [] ] );
        }

    }

    /**
     * @param $filesInfo
     * @param $segments
     *
     * @return array
     */
    private function mergeFileInfoWithSegments($filesInfo, $segments) {

        $merged = [];

        foreach ($filesInfo['files'] as $fileInfo){
            $merged[] = array_merge(
                    $fileInfo,
                    ['segments' => $segments[$fileInfo['id']]['segments']]
            );
        }

        return $merged;
    }

    /**
     * @param array      $segments_id
     * @param            $step
     * @param array|null $filter
     *
     * @return array
     */
    protected function _getPaginationLinks( array $segments_id, $step, array $filter = null ) {

        $url = parse_url( $_SERVER[ 'REQUEST_URI' ] );

        $links = [
                "base" => INIT::$HTTPHOST,
                "self" => $_SERVER[ 'REQUEST_URI' ],
        ];

        $filter_query = http_build_query( [ 'filter' => array_filter( $filter ) ] );
        if ( $this->chunk->job_last_segment > end( $segments_id ) ) {
            $links[ 'next' ] = $url[ 'path' ] . "?ref_segment=" . end( $segments_id ) . ( $step != 20 ? "&step=" . $step : null ) . ( !empty( $filter_query ) ? "&" . $filter_query : null );
        }

        if ( $this->chunk->job_first_segment < reset( $segments_id ) ) {
            $links[ 'prev' ] = $url[ 'path' ] . "?ref_segment=" . ( reset( $segments_id ) - ( $step + 1 ) * 2 ) . ( $step != 20 ? "&step=" . $step : null ) . ( !empty( $filter_query ) ? "&" . $filter_query : null );
        }

        return $links;

    }

    /**
     * Change the response json to remove source_page property and change it to revision number.
     *
     * @param array $files
     * @param array $ttlArray
     *
     * @return array
     */
    protected function _formatSegments( $files, array $ttlArray ) {
        $outputArray = [];

//
//        {
//            "files": [
//     {
//         "id": "5561056",
//        "first_segment": "1758812487",
//        "last_segment": "1758812547",
//        "file_name": "MateCat - migrazioni database con phinx.docx",
//        "raw_words": "806.00",
//        "weighted_words": "586.60",
//        "metadata": []
//      }
//    ],
//    "first_segment": "1758812487",
//    "last_segment": "1758812547"
//}

        foreach ( $files as $k0 => $file ) {

            if ( !isset( $outputArray [ $k0 ] [ 'filename' ] ) ) {
                $outputArray [ $k0 ] [ 'filename' ] = $file[ 'filename' ];
            }

            foreach ( $file[ 'segments' ] as $k1 => $segment ) {
                if ( !empty( $segment->issues ) ) {
                    foreach ( $segment->issues as $k2 => $issue ) {
                        $segment->issues[ $k2 ][ 'revision_number' ] = ReviewUtils::sourcePageToRevisionNumber(
                                $segment->issues[ $k2 ][ 'source_page' ]
                        );
                        unset( $segment->issues[ $k2 ][ 'source_page' ] );

                        if ( !empty( $issue->comments ) ) {
                            $renderedIssueComments = [];
                            foreach ( $issue->comments as $k3 => $comment ) {
                                $renderedIssueComments [] = ( new TranslationIssueComment() )->renderItem( (object)$comment );
                            }
                            $issue->comments = null;
                            $issue->comments = $renderedIssueComments;
                        }
                    }
                }

                // Time to edit array
                $tte = $this->getTteArrayForSegment( $ttlArray, $segment->sid );

                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ]                                = $file[ 'segments' ] [ $k1 ]->toArray();
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'time_to_edit' ]             = $tte[ 'total' ];
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'time_to_edit_translation' ] = $tte[ 'translation' ];
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'time_to_edit_revise' ]      = $tte[ 'revise' ];
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'time_to_edit_revise_2' ]    = $tte[ 'revise_2' ];
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'secs_per_word' ]            = $this->getSecsPerWord( $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] );
                $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'revision_number' ]          = ReviewUtils::sourcePageToRevisionNumber(
                        $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'source_page' ]
                );
                unset( $outputArray [ $k0 ] [ 'segments' ] [ $k1 ] [ 'source_page' ] );
            }
        }

        return $outputArray;
    }

    /**
     * @param array $tteArray
     * @param int   $sid
     *
     * @return array
     */
    private function getTteArrayForSegment( $tteArray, $sid ) {

        $return = [];

        foreach ( $tteArray as $tte ) {
            if ( (int)$sid === (int)$tte->id_segment ) {
                switch ( $tte->source_page ) {
                    case '1':
                        $key = 'translation';
                        break;

                    case '2':
                        $key = 'revise';
                        break;

                    case '3':
                        $key = 'revise_2';
                        break;

                }

                $return[ $key ] = (int)$tte->tte;
            }
        }

        if ( false === isset( $return[ 'revise' ] ) ) {
            $return[ 'revise' ] = 0;
        }

        if ( false === isset( $return[ 'revise_2' ] ) ) {
            $return[ 'revise_2' ] = 0;
        }

        $return[ 'total' ] = $return[ 'translation' ] + $return[ 'revise' ] + $return[ 'revise_2' ];

        return $return;
    }

    /**
     * @param $outputArray
     *
     * @return float|int
     */
    private function getSecsPerWord( $outputArray ) {
        $tte            = ( $outputArray[ 'time_to_edit' ] ) / 1000;
        $raw_word_count = $outputArray[ 'raw_word_count' ];

        return $tte / $raw_word_count;
    }


    public function general() {
        $project = $this->chunk->getProject();
        $this->response->json( [
                'project' => $project,
                'job'     => $this->chunk,
        ] );
    }

    public function versions() {
        $dao      = new ArchivedQualityReportDao();
        $versions = $dao->getAllByChunk( $this->chunk );
        $response = [];

        foreach ( $versions as $version ) {
            $response[] = [
                    'id'             => (int)$version->id,
                    'version_number' => (int)$version->version,
                    'created_at'     => \Utils::api_timestamp( $version->create_date ),
                    'quality-report' => json_decode( $version->quality_report )
            ];
        }

        $this->response->json( [ 'versions' => $response ] );

    }

    protected function afterConstruct() {
        $Validator  = new ChunkPasswordValidator( $this );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
    }

}