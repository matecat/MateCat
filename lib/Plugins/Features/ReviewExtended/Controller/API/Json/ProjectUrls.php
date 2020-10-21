<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:57
 */

namespace Features\ReviewExtended\Controller\API\Json;

use Chunks_ChunkStruct;
use Features\ReviewExtended\ReviewUtils;
use LQA\ChunkReviewDao;
use Routes;

class ProjectUrls extends \API\V2\Json\ProjectUrls {

    protected function generateChunkUrls( $record ) {

        if ( !array_key_exists( $record[ 'jpassword' ], $this->chunks ) ) {
            $this->chunks[ $record[ 'jpassword' ] ] = 1;

            $this->jobs[ $record[ 'jid' ] ][ 'chunks' ][ $record[ 'jpassword' ] ] = [
                    'password'      => $record[ 'jpassword' ],
                    'translate_url' => $this->translateUrl( $record ),
            ];

            $reviews = ( new ChunkReviewDao() )->findChunkReviews( new Chunks_ChunkStruct( [ 'id' => $record[ 'jid' ], 'password' => $record[ 'jpassword' ] ] ) );

            foreach ( $reviews as $review ) {
                $revisionNumber = ReviewUtils::sourcePageToRevisionNumber( $review->source_page );
                $reviseUrl      = Routes::revise(
                        $record[ 'name' ],
                        $record[ 'jid' ],
                        $review->review_password,
                        $record[ 'source' ],
                        $record[ 'target' ],
                        [ 'revision_number' => $revisionNumber ]
                );

                $this->jobs[ $record[ 'jid' ] ][ 'chunks' ][ $record[ 'jpassword' ] ] [ 'revise_urls' ] [] = [
                        'revision_number' => $revisionNumber,
                        'url'             => $reviseUrl
                ];
            }
        }
    }

}