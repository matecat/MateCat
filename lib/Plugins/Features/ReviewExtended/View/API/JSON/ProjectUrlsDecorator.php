<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 20/12/2017
 * Time: 12:08
 */

namespace Features\ReviewExtended\View\API\JSON;


use API\V2\Json\ProjectUrls;
use Chunks_ChunkStruct;
use LQA\ChunkReviewDao;


class ProjectUrlsDecorator extends ProjectUrls {

    public function reviseUrl( $record ) {

        $reviewChunk = ( new ChunkReviewDao() )->findChunkReviews(
                new Chunks_ChunkStruct( [ 'id' => $record[ 'jid' ], 'password' => $record[ 'jpassword' ] ] )
        )[ 0 ];

        return \Routes::revise(
                $record[ 'name' ],
                $record[ 'jid' ],
                ( !empty( $reviewChunk ) ? $reviewChunk->review_password : $record[ 'jpassword' ] ),
                $record[ 'source' ],
                $record[ 'target' ]
        );

    }

}