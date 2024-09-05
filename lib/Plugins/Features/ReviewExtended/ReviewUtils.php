<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */

namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use Constants;
use Constants_TranslationStatus;
use Exception;
use LQA\ChunkReviewDao;
use LQA\ModelStruct;

class ReviewUtils {

    /**
     * @param $number
     *
     * @return string|null
     */
    public static function sourcePageToTranslationStatus( $number = null ): ?string {
        $statuses = [
                Constants::SOURCE_PAGE_TRANSLATE  => Constants_TranslationStatus::STATUS_TRANSLATED,
                Constants::SOURCE_PAGE_REVISION   => Constants_TranslationStatus::STATUS_APPROVED,
                Constants::SOURCE_PAGE_REVISION_2 => Constants_TranslationStatus::STATUS_APPROVED2
        ];

        return empty( $number ) ? null : $statuses[ $number ];
    }

    /**
     *
     * @param int|null $number
     *
     * @return int
     */
    public static function revisionNumberToSourcePage( int $number = null ): int {
        return ( !empty( $number ) ) ? $number + 1 : 1;
    }

    /**
     * @param ?int $number
     *
     * @return ?int
     */
    public static function sourcePageToRevisionNumber( int $number = null ): ?int {
        return ( ( (int)$number - 1 ) < 1 ) ? null : $number - 1;
    }

    /**
     * @param ModelStruct $lqaModel
     * @param int         $sourcePage
     *
     * @return int
     * @throws Exception
     */
    public static function filterLQAModelLimit( ModelStruct $lqaModel, int $sourcePage ): int {
        $limit = $lqaModel->getLimit();

        if ( is_array( $limit ) ) {
            /**
             * Limit array index equals to $source_page -2.
             */
            return $limit[ $sourcePage - 2 ] ?? end( $limit );
        }

        return $limit;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return int[]
     */
    public static function validRevisionNumbers( Chunks_ChunkStruct $chunk ): array {
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk );

        return array_map( function ( $chunkReview ) {
            return self::sourcePageToRevisionNumber( $chunkReview->source_page );
        }, $chunkReviews );
    }
}