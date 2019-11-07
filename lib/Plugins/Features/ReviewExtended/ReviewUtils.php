<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */


namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use Features\ReviewExtended\Model\ChunkReviewDao as ReviewExtendedChunkReviewDao;
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

            // check if the current advancement_wc corresponds to correct advancement word count
            if ( $chunkReview->advancement_wc !== self::getCorrectAdvancementWC( $chunkReview ) ) {

                /** @var \Projects_ProjectStruct $project */
                $project         = $chunkReview->getChunk()->getProject();
                $revisionFactory = \RevisionFactory::initFromProject( $project );
                $model           = $revisionFactory->getChunkReviewModel( $chunkReview );
                $model->recountAndUpdatePassFailResult( $project );

                $msg = "Wrong advancement word count found for project with ID: " . $project->id . ". Recount done.";

                \Utils::sendErrMailReport( $msg );
                \Log::doJsonLog( $msg );
            }

            $statsArray[ 'revises' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunkReview->source_page ),
                    'advancement_wc'  => $chunkReview->advancement_wc
            ];
        }

        return $statsArray;
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return int
     */
    private static function getCorrectAdvancementWC( ChunkReviewStruct $chunkReview ) {
        $chunkReviewDao = new ReviewExtendedChunkReviewDao();

        return $chunkReviewDao->recountAdvancementWords( $chunkReview->getChunk(), $chunkReview->source_page );
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
        return ( ($number - 1) < 1 ) ? null : $number - 1;
    }

    /**
     * @param ModelStruct $lqaModel
     * @param string $sourcePage
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
