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

    /**
     * @param array $statsArray
     * @param array $chunkReviews
     *
     * @return array
     * @throws \Exception
     */
    public static function formatStats( $statsArray, $chunkReviews ) {
        $statsArray [ 'revises' ] = [];

        /** @var ChunkReviewStruct $chunkReview */
        foreach ( $chunkReviews as $chunkReview ) {
            $statsArray[ 'revises' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunkReview->source_page ),
                    'advancement_wc'  => (float)$chunkReview->advancement_wc
            ];
        }

        return $statsArray;
    }

    /**
     *
     * @param null $number
     *
     * @return int
     */
    public static function revisionNumberToSourcePage( $number = null ) {
        return ( !empty( $number ) ) ? $number + 1 : 1;
    }

    /**
     * @param int $number
     *
     * @return int|null
     */
    public static function sourcePageToRevisionNumber( $number ) {
        return ( ( $number - 1 ) < 1 ) ? null : $number - 1;
    }

    /**
     * @param ModelStruct $lqaModel
     * @param string      $sourcePage
     *
     * @return array|mixed
     * @throws \Exception
     */
    public static function filterLQAModelLimit( ModelStruct $lqaModel, $sourcePage ) {
        $limit = $lqaModel->getLimit();

        if ( is_array( $limit ) ) {
            /**
             * Limit array index equals to $source_page -2.
             */
            return isset( $limit[ $sourcePage - 2 ] ) ? $limit[ $sourcePage - 2 ] : end( $limit );
        }

        return $limit;
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