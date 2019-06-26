<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use catController;
use Chunks_ChunkStruct;
use Constants;
use Exceptions\NotFoundException;
use Features;
use Features\SecondPassReview\Controller\API\Json\ProjectUrls;
use Features\SecondPassReview\Model\ChunkReviewModel;
use Features\SecondPassReview\Utils;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use Klein\Klein;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectDao;
use Users_UserStruct;

class SecondPassReview extends BaseFeature {
    const FEATURE_CODE = 'second_pass_review' ;

    protected static $dependencies = [
            Features::REVIEW_EXTENDED
    ];

    public static function projectUrls( $formatted ) {
        $projectUrlsDecorator = new ProjectUrls( $formatted->getData() );
        return $projectUrlsDecorator;
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST',
                'Features\SecondPassReview\Controller\ReviewsController', 'createReview' );
    }

    public function chunkReviewRecordCreated($chunkReview) {
        // This is needed to properly populate advancement wc for ICE matches
        ( new ChunkReviewModel( $chunkReview ))->recountAndUpdatePassFailResult();
    }

    /**
     * This callback is necessary so that ICE matches are included in advancement_wc.
     *
     * @param $project_id
     * @param $_analyzed_report
     */
    public function afterTMAnalysisCloseProject( $project_id, $_analyzed_report ) {
        $project = Projects_ProjectDao::findById( $project_id );
        $chunk_ids = array_map( function( $chunk ) {
            return [ $chunk->id, $chunk->password ];
        }, $project->getChunks() );

        $chunkReviews = (new ChunkReviewDao())->findAllChunkReviewsByChunkIds( $chunk_ids );
        foreach ($chunkReviews as $chunkReview ) {
            $model = new ChunkReviewModel( $chunkReview ) ;
            $model->recountAndUpdatePassFailResult() ;
        }
    }

    public function catControllerChunkFound( catController $controller ) {
        if ( !$controller->isRevision() ) {
            return ;
        }

        if ( $controller->getRevisionNumber() > 1 ) {
            $chunk_review = ( new ChunkReviewDao() )->findByJobIdPasswordAndSourcePage(
                    $controller->getChunk()->id,
                    $controller->getChunk()->password,
                    Utils::revisionNumberToSourcePage( $controller->getRevisionNumber() )
            );

            if ( empty( $chunk_review ) ) {
                throw new NotFoundException("This revision did not start yet: " . $controller->getRevisionNumber() ) ;
            }
        }
    }

    public function filterSourcePage( $sourcePage ) {
        $_from_url = parse_url( @$_SERVER['HTTP_REFERER'] );
        $matches = null ;
        preg_match( '/revise([2-9])?\//s' , $_from_url['path'], $matches ) ;
        if  ( count( $matches ) > 1 ) {
            $sourcePage = Utils::revisionNumberToSourcePage( $matches[ 1 ] ) ;
        }
        return $sourcePage ;
    }

    public function filterStatsResponse( $inputStats, $options ) {
        /** @var Chunks_ChunkStruct $chunk */
        $chunk = $options['chunk'] ;
        $chunkReviews = ( new ChunkReviewDao() )->findAllChunkReviewsByChunkIds([ [ $chunk->id, $chunk->password ] ] );

        return SecondPassReview\Utils::formatStats( $inputStats, $chunkReviews ) ;
    }

    /**
     *
     * @param $project
     */
    public function filter_manage_single_project( $project ) {
        $chunks = [];

        foreach( $project['jobs'] as $job ) {
            $chunks[] = [ $job['id'], $job['password'] ];
        }

        $chunk_reviews = [] ;

        foreach( ( new ChunkReviewDao() )->findAllChunkReviewsByChunkIds( $chunks ) as $chunk_review ) {
            $key = $chunk_review->id_job . $chunk_review->password  ;
            if ( !isset( $chunk_reviews[ $key ] )) {
                $chunk_reviews[ $key ] = [] ;
            }
            $chunk_reviews[ $key ] [] = $chunk_review ;
        }

        foreach( $project['jobs'] as $kk => $job ) {
            $key = $job['id'] . $job['password'] ;

            if ( isset( $chunk_reviews[ $key ] ) ) {
                $project['jobs'][$kk][ 'second_pass_review' ] = array_values( array_filter( array_map( function(ChunkReviewStruct $chunk_review ) {
                    if ( $chunk_review->source_page > Constants::SOURCE_PAGE_REVISION ) {
                        return $chunk_review->password ;
                    }
                }, $chunk_reviews[$key] ))) ;

                if ( !isset( $project['jobs'][ $kk ] [ 'stats' ] ['reviews'] ) ) {
                    $project['jobs'][ $kk ] [ 'stats' ] = Utils::formatStats( $project['jobs'][ $kk ] [ 'stats' ], $chunk_reviews[ $key ] ) ;
                }
            }
        }

        return $project ;
    }

    public function filterGetSegmentsResult($data, Chunks_ChunkStruct $chunk ) {
        reset( $data['files'] ) ;

        $firstFile = current( $data['files'] ) ;
        $lastFile  = end( $data['files'] );
        $firstSid = $firstFile['segments'][0]['sid'];

        $lastSegment = end( $lastFile['segments'] );
        $lastSid = $lastSegment['sid'];

        $segment_translation_events = ( new SegmentTranslationEventDao())->getLatestEventsInSegmentInterval(
                $chunk->id, $firstSid, $lastSid );

        $by_id_segment = [] ;
        foreach( $segment_translation_events as $record ) {
            $by_id_segment[ $record->id_segment ] = $record ;
        }

        foreach ( $data['files'] as $file => $content ) {
            foreach( $content['segments'] as $key => $segment ) {
                $data ['files'] [ $file ] ['segments'] [ $key ] ['revision_number'] = Utils::sourcePageToRevisionNumber(
                        $by_id_segment[ $segment['sid'] ]->source_page
                );
            }
        }

        return $data ;
    }
}