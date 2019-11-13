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
     * @param array $options
     *
     * @return array
     * @throws \Exception
     */
    public static function formatStats( $statsArray, $chunkReviews, $options = [] ) {
        $statsArray [ 'revises' ] = [];

        /** @var ChunkReviewStruct $chunkReview */
        foreach ( $chunkReviews as $chunkReview ) {

            $advancementWcAsFloat        = floatval( $chunkReview->advancement_wc );
            $correctAdvancementWCAsFloat = floatval( $correctAdvancementWC = self::getCorrectAdvancementWC( $chunkReview ) );

            // check if the current advancement_wc corresponds to correct advancement word count
            if ( $advancementWcAsFloat !== $correctAdvancementWCAsFloat ) {

                /** @var \Projects_ProjectStruct $project */
                $project         = $chunkReview->getChunk()->getProject();
                $revisionFactory = \RevisionFactory::initFromProject( $project );
                $model           = $revisionFactory->getChunkReviewModel( $chunkReview );
                $model->recountAndUpdatePassFailResult( $project );

                $chunkReview->advancement_wc = $correctAdvancementWC;

                $segmentId = isset($options['segmentId']) ? $options['segmentId'] : null;
                $requestUri = isset($options['requestUri']) ? $options['requestUri'] : null;

                $htmlMessageForEmail = self::getHtmlMessageForEmail($project, $chunkReview, $advancementWcAsFloat, $correctAdvancementWCAsFloat, $segmentId, $requestUri);
                $arrayMessageForLogs  = self::getArrayMessageForLogs($project, $chunkReview, $advancementWcAsFloat, $correctAdvancementWCAsFloat, $segmentId, $requestUri);

                //\Utils::sendErrMailReport( $htmlMessageForEmail );
                \Log::doJsonLog( $arrayMessageForLogs );
            }

            $statsArray[ 'revises' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunkReview->source_page ),
                    'advancement_wc'  => $chunkReview->advancement_wc
            ];
        }

        return $statsArray;
    }

    /**
     * @param                   $project
     * @param ChunkReviewStruct $chunkReview
     * @param                   $advancementWcAsFloat
     * @param                   $correctAdvancementWCAsFloat
     * @param null              $segmentId
     *
     * @param null              $requestUri
     *
     * @return string
     */
    private static function getHtmlMessageForEmail( $project, ChunkReviewStruct $chunkReview, $advancementWcAsFloat, $correctAdvancementWCAsFloat, $segmentId = null, $requestUri = null)
    {
        $msgEmail = "<p>Wrong advancement word count found for project with ID: " . $project->id . ".</p>";
        $msgEmail .= "<p>--------------------------------</p>";
        $msgEmail .= "<ul>";
        $msgEmail .= "<li>PROJECT ID: " . $project->id . "</li>";
        $msgEmail .= "<li>JOB ID: " . $chunkReview->getChunk()->id . "</li>";

        if(null !== $segmentId) {
            $msgEmail .= "<li>SEGMENT ID: " . $segmentId . "</li>";
        }

        if(null !== $requestUri) {
            $msgEmail .= "<li>REQUEST URI: " . $requestUri . "</li>";
        }

        $msgEmail .= "<li>ACTUAL SOURCE PAGE: " . $chunkReview->source_page . "</li>";
        $msgEmail .= "<li>ACTUAL ADVANCED WC: " . $advancementWcAsFloat . "</li>";
        $msgEmail .= "<li>CALCULATED WC: " . $correctAdvancementWCAsFloat . "</li>";
        $msgEmail .= "</ul>";
        $msgEmail .= "<p>--------------------------------</p>";
        $msgEmail .= "<p>Recount done.</p>";

        return $msgEmail;
    }

    /**
     * @param                   $project
     * @param ChunkReviewStruct $chunkReview
     * @param                   $advancementWcAsFloat
     * @param                   $correctAdvancementWCAsFloat
     * @param null              $segmentId
     *
     * @param null              $requestUri
     *
     * @return array
     */
    private static function getArrayMessageForLogs( $project, ChunkReviewStruct $chunkReview, $advancementWcAsFloat, $correctAdvancementWCAsFloat, $segmentId = null, $requestUri = null)
    {
        $msgArray = [];
        $msgArray['message'] = "Wrong advancement word count found for project with ID: " . $project->id . ". Recount done.";
        $msgArray['payload'] = [];
        $msgArray['payload']['PROJECT_ID'] = $project->id;
        $msgArray['payload']['JOB_ID'] = $chunkReview->getChunk()->id;

        if(null !== $segmentId) {
            $msgArray['payload']['SEGMENT_ID'] = $segmentId;
        }

        if(null !== $requestUri) {
            $msgArray['payload']['REQUEST_RUI'] = $requestUri;
        }

        $msgArray['payload']['ACTUAL_SOURCE_PAGE'] = $chunkReview->source_page;
        $msgArray['payload']['ACTUAL_ADVANCED_WC'] = $advancementWcAsFloat;
        $msgArray['payload']['CALCULATED_WC'] = $correctAdvancementWCAsFloat;

        return $msgArray;
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