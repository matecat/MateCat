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
use Exceptions\NotFoundException;
use Features;
use Features\SecondPassReview\Controller\API\Json\ProjectUrls;
use Features\SecondPassReview\Utils;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use Klein\Klein;
use LQA\ChunkReviewDao;

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


    /**
     *
     * @param $project
     */
    public function filter_manage_single_project( $project ) {
        $chunks = array();

        foreach( $project['jobs'] as $job ) {
            $chunks[] = array( $job['id'], $job['password'] );
        }

        $chunk_reviews = ( new ChunkReviewDao() )->findAllChunkReviewsByChunkIds( $chunks );

        foreach( $project['jobs'] as $kk => $job ) {
            /**
             * Inner cycle to match chunk_reviews records and modify
             * the data structure.
             */
            foreach( $chunk_reviews as $chunk_review ) {
                if ( $chunk_review->id_job == $job['id'] && $chunk_review->password == $job['password'] ) {
                    // TODO: change this revision number to an array of review passwords
                    if ( $chunk_review->source_page == 3 ) {
                        $project['jobs'][$kk][ 'second_pass_review' ][] = $chunk_review->review_password ;
                    }
                    if ( !isset( $project['jobs'][ $kk ] [ 'stats' ] ['reviews'] ) ) {
                        $project['jobs'][ $kk ] [ 'stats' ] = Utils::formatStats( $project['jobs'][ $kk ] [ 'stats' ], $chunk_reviews ) ;
                    }
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