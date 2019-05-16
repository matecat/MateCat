<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:57
 */

namespace Features\SecondPassReview\Controller\API\Json ;

use Features\SecondPassReview;
use LQA\ChunkReviewDao;
use Routes;

class ProjectUrls extends \API\V2\Json\ProjectUrls {

    protected function generateChunkUrls( $record ){

        if ( !array_key_exists( $record['jpassword'], $this->chunks ) ) {
            $this->chunks[ $record['jpassword'] ] = 1 ;

            $this->jobs[ $record['jid'] ][ 'chunks' ][ $record['jpassword'] ] = array(
                    'password'      => $record['jpassword'],
                    'translate_url' => $this->translateUrl( $record ),
            );

            $reviews = ( new ChunkReviewDao())->findAllChunkReviewsByChunkIds([[
                    $record['jid'], $record['jpassword'] ]]) ;

            foreach( $reviews as $review ) {
                $revisionNumber = SecondPassReview\Utils::sourcePageToRevisionNumber( $review->source_page ) ;
                $reviseUrl = Routes::revise(
                        $record['name'],
                        $record['jid'],
                        $review->review_password,
                        $record['source'],
                        $record['target'],
                        [ 'revision_number' => $revisionNumber ]
                );

                $this->jobs[ $record['jid'] ][ 'chunks' ][ $record['jpassword'] ] [ 'revise_urls' ] [] = [
                        'revision_number' => $revisionNumber,
                        'url'             => $reviseUrl
                ] ;
            }
        }
    }

}