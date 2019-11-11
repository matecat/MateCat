<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */


namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use LQA\ModelStruct;

class ReviewUtils {

    public static function formatStats( $statsArray, $chunkReviews ) {
        $statsArray [ 'revises' ] = [];

        /** @var ChunkReviewStruct $chunkReview */
        foreach ( $chunkReviews as $chunkReview ) {

            // recount if advancement_wc < 0
            if( $chunkReview->advancement_wc < 0 ){

                /** @var \Projects_ProjectStruct $project */
                $project = $chunkReview->getChunk()->getProject();
                $revisionFactory = \RevisionFactory::initFromProject( $project );
                $model = $revisionFactory->getChunkReviewModel( $chunkReview ) ;
                $model->recountAndUpdatePassFailResult( $project );

                \Log::doJsonLog("Negative advancement_wc found for project with ID: ".$project->id.". WC recount done.");
            }

            $statsArray[ 'revises' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunkReview->source_page ),
                    'advancement_wc'  => $chunkReview->advancement_wc
            ];
        }

        return $statsArray;
    }

    /**
     *
     * @param $number
     *
     * @return int
     */
    public static function revisionNumberToSourcePage( $number = null ) {
        if ( ! empty( $number ) ) {
            return $number + 1;
        }

        return 1;
    }

    /**
     * @param $number
     *
     * @return int|null
     */
    public static function sourcePageToRevisionNumber( $number ) {
        if ( $number - 1 < 1 ) {
            return null;
        }

        return $number - 1;
    }

    public static function filterLQAModelLimit( ModelStruct $lqaModel, $sourcePage ) {
        $limit = $lqaModel->getLimit();

        if ( is_array( $limit ) ) {
            /**
             * Limit array index equals to $source_page -2.
             */
            return isset( $limit[ $sourcePage - 2 ] ) ? $limit[ $sourcePage - 2 ] : end( $limit );
        } else {
            return $limit;
        }
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return array
     */
    public static function validRevisionNumbers( Chunks_ChunkStruct $chunk ) {
        $chunkReviews         = ( new ChunkReviewDao() )->findChunkReviews( $chunk );
        $validRevisionNumbers = array_map( function ( $chunkReview ) {
            return self::sourcePageToRevisionNumber( $chunkReview->source_page );
        }, $chunkReviews );

        return $validRevisionNumbers;
    }

}