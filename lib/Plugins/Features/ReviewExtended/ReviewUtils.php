<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */

namespace Plugins\Features\ReviewExtended;

use Exception;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ModelStruct;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class ReviewUtils {

    /**
     * @param $number
     *
     * @return string|null
     */
    public static function sourcePageToTranslationStatus( $number = null ): ?string {
        $statuses = [
                SourcePages::SOURCE_PAGE_TRANSLATE  => TranslationStatus::STATUS_TRANSLATED,
                SourcePages::SOURCE_PAGE_REVISION   => TranslationStatus::STATUS_APPROVED,
                SourcePages::SOURCE_PAGE_REVISION_2 => TranslationStatus::STATUS_APPROVED2
        ];

        return empty( $number ) ? null : $statuses[ $number ];
    }

    /**
     *
     * @param int|null $number
     *
     * @return int
     */
    public static function revisionNumberToSourcePage( ?int $number = null ): int {
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
     * @param JobStruct $chunk
     *
     * @return int[]
     */
    public static function validRevisionNumbers( JobStruct $chunk ): array {
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $chunk );

        return array_map( function ( $chunkReview ) {
            return self::sourcePageToRevisionNumber( $chunkReview->source_page );
        }, $chunkReviews );
    }
}