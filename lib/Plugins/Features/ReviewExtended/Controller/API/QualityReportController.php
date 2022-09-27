<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Features\ReviewExtended\Controller\API;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use API\V2\BaseChunkController;
use Chunks_ChunkStruct;
use Constants_JobStatus;
use Features\ReviewExtended\Model\ArchivedQualityReportDao;
use Features\ReviewExtended\Model\QualityReportModel;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions\Model\TranslationEventDao;
use Files\FilesInfoUtility;
use INIT;
use Projects_ProjectStruct;
use QualityReport\QualityReportSegmentModel;

class QualityReportController extends BaseChunkController {

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

        $this->return404IfTheJobWasDeleted();
        $this->model = new QualityReportModel( $this->chunk );
        $this->model->setDateFormat( 'c' );

        $this->response->json( [
                'quality-report' => $this->model->getStructure()
        ] );
    }

    public function segments() {
        $this->return404IfTheJobWasDeleted();

        return $this->renderSegments();
    }

    /**
     * @param bool $isForUI
     *
     * @throws \Exception
     */
    protected function renderSegments( $isForUI = false ) {

        $this->project = $this->chunk->getProject();

        $ref_segment = (int)$this->request->param( 'ref_segment' );
        $where       = $this->request->param( 'where' );
        $step        = (int)$this->request->param( 'step' );
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

            $segmentTranslationEventDao = new TranslationEventDao();
            $ttlArray                   = $segmentTranslationEventDao->setCacheTTL( 60 * 5 )->getTteForSegments( $segments_ids, $this->chunk->id );
            $segments                   = $qrSegmentModel->getSegmentsForQR( $segments_ids, $isForUI );

            $filesInfoUtility = new FilesInfoUtility( $this->chunk );
            $filesInfo        = $filesInfoUtility->getInfo(false);

            $segments = $this->_formatSegments( $segments, $ttlArray, $filesInfo );

            $this->response->json( [
                    'segments'      => $segments,
                    'first_segment' => (int)$filesInfo[ 'first_segment' ],
                    'last_segment'  => (int)$filesInfo[ 'last_segment' ],
                    '_params'       => [
                            'ref_segment' => !empty( $ref_segment ) ? $ref_segment : null,
                            'where'       => $this->request->param( 'where' ),
                            'step'        => !empty( $this->request->param( 'step' ) ) ? $step : null,
                            'filter'      => $this->request->param( 'filter' ),
                    ],
                    '_links'        => $this->_getPaginationLinks( $segments_ids, $step, $filter )
            ] );

        } else {
            $this->response->json( [ 'segments' => [] ] );
        }
    }

    /**
     * @param array      $segments_id
     * @param int        $step
     * @param array|null $filter
     *
     * @return array
     */
    private function _getPaginationLinks( array $segments_id, $step, array $filter = null ) {

        $url   = parse_url( $_SERVER[ 'REQUEST_URI' ] );
        $total = count( $this->chunk->getSegments() );
        $pages = ceil( $total / $step );

        $links = [
                "base"            => INIT::$HTTPHOST,
                'last_segment_id' => (int)end( $segments_id ),
                "pages"           => $pages,
                "items_per_page"  => $step,
                "total_items"     => $total,
                "self"            => $_SERVER[ 'REQUEST_URI' ],
                "next"            => null,
                "prev"            => null,
        ];

        $filter_query = http_build_query( [ 'filter' => array_filter( empty( $filter ) ? [] : $filter ) ] );
        if ( $this->chunk->job_last_segment > end( $segments_id ) ) {
            $links[ 'next' ] = $url[ 'path' ] . "?ref_segment=" . end( $segments_id ) . ( $step != 20 ? "&step=" . $step : null ) . ( !empty( $filter_query ) ? "&" . $filter_query :
                            null );
        }

        if ( $this->chunk->job_first_segment < reset( $segments_id ) ) {
            $links[ 'prev' ] = $url[ 'path' ] . "?ref_segment=" . ( reset( $segments_id ) - ( $step + 1 ) ) . ( $step != 20 ? "&step=" . $step : null ) . ( !empty(
                    $filter_query ) ? "&" . $filter_query : null );
        }

        return $links;
    }

    /**
     * Change the response json to remove source_page property and change it to revision number.
     *
     * @param array $segments
     * @param array $ttlArray
     * @param array $filesInfo
     *
     * @return array
     */
    private function _formatSegments( $segments, array $ttlArray, array $filesInfo ) {
        $outputArray = [];

        foreach ( $segments as $index => $segment ) {

            $seg                                 = [];
            $seg[ 'comments' ]                   = $segment->comments;
            $seg[ 'dataRefMap' ]                 = $segment->dataRefMap;
            $seg[ 'edit_distance' ]              = $segment->edit_distance;
            $seg[ 'ice_locked' ]                 = $segment->ice_locked;
            $seg[ 'ice_modified' ]               = $segment->ice_modified;
            $seg[ 'is_pre_translated' ]          = $segment->is_pre_translated;
            $seg[ 'issues' ]                     = $segment->issues;
            $seg[ 'last_revisions' ]             = $segment->last_revisions;
            $seg[ 'last_translation' ]           = $segment->last_translation;
            $seg[ 'locked' ]                     = $segment->locked;
            $seg[ 'match_type' ]                 = $segment->match_type;
            $seg[ 'parsed_time_to_edit' ]        = $segment->parsed_time_to_edit;
            $seg[ 'pee' ]                        = $segment->pee;
            $seg[ 'pee_translation_revise' ]     = $segment->pee_translation_revise;
            $seg[ 'pee_translation_suggestion' ] = $segment->pee_translation_suggestion;
            $seg[ 'raw_word_count' ]             = $segment->raw_word_count;
            $seg[ 'secs_per_word' ]              = $segment->secs_per_word;
            $seg[ 'segment' ]                    = $segment->segment;
            $seg[ 'segment_hash' ]               = $segment->segment_hash;
            $seg[ 'id' ]                         = (int)$segment->sid;
            $seg[ 'source_page' ]                = $segment->source_page;
            $seg[ 'status' ]                     = $segment->status;
            $seg[ 'suggestion' ]                 = $segment->suggestion;
            $seg[ 'suggestion_match' ]           = $segment->suggestion_match;
            $seg[ 'suggestion_source' ]          = $segment->suggestion_source;
            $seg[ 'target' ]                     = $segment->target;
            $seg[ 'translation' ]                = $segment->translation;
            $seg[ 'version' ]                    = $segment->version;
            $seg[ 'version_number' ]             = $segment->version_number;
            $seg[ 'warnings' ]                   = $segment->warnings;

            // add fileInfo
            foreach ( $filesInfo[ 'files' ] as $file ) {
                if ( $file[ 'id' ] == $segment->id_file ) {
                    $seg[ 'file' ] = $file;
                }
            }

            // add tte
            $tte                               = $this->getTteArrayForSegment( $ttlArray, $segment->sid );
            $seg[ 'time_to_edit' ]             = $tte[ 'total' ];
            $seg[ 'time_to_edit_translation' ] = $tte[ 'translation' ];
            $seg[ 'time_to_edit_revise' ]      = $tte[ 'revise' ];
            $seg[ 'time_to_edit_revise_2' ]    = $tte[ 'revise_2' ];
            $seg[ 'secs_per_word' ]            = $this->getSecsPerWord( $segment );
            $seg[ 'revision_number' ]          = ReviewUtils::sourcePageToRevisionNumber( $segment->source_page );

            ksort( $seg );

            $outputArray[] = $seg;
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

        if ( false === isset( $return[ 'translation' ] ) ) {
            $return[ 'translation' ] = 0;
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
     * @param $segment
     *
     * @return float|int
     */
    private function getSecsPerWord( $segment ) {
        $tte            = ( $segment->time_to_edit ) / 1000;
        $raw_word_count = $segment->raw_word_count;

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