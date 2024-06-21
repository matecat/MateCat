<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use BasicFeatureStruct;
use Chunks_ChunkStruct;
use createProjectController;
use Exception;
use Features;
use Features\ReviewExtended\Controller\API\Json\ProjectUrls;
use Features\ReviewExtended\ReviewUtils as ReviewUtils;
use Features\SecondPassReview\Model\ChunkReviewModel;
use Features\TranslationVersions\Model\TranslationEventDao;
use Klein\Klein;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use NewController;
use Projects_ProjectDao;
use Projects_ProjectStruct;

class SecondPassReview extends BaseFeature {
    const FEATURE_CODE = 'second_pass_review';

    protected static $dependencies = [
            Features::REVIEW_EXTENDED
    ];

    public static function projectUrls( $formatted ) {
        return new ProjectUrls( $formatted->getData() );
    }

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST',
                'Features\SecondPassReview\Controller\ReviewsController', 'createReview' );
    }

    /**
     * @param ChunkReviewStruct       $chunkReview
     * @param Projects_ProjectStruct $projectStruct
     *
     * @throws Exception
     */
    public function chunkReviewRecordCreated( ChunkReviewStruct $chunkReview, Projects_ProjectStruct $projectStruct ) {
        // This is needed to properly populate advancement wc for ICE matches
        ( new ChunkReviewModel( $chunkReview ) )->recountAndUpdatePassFailResult( $projectStruct );
    }

    public function filterGetSegmentsResult( $data, Chunks_ChunkStruct $chunk ) {

        if ( empty( $data[ 'files' ] ) ){
            // this means that there are no more segments after
            return $data;
        }

        reset( $data[ 'files' ] );

        $firstFile = current( $data[ 'files' ] );
        $lastFile  = end( $data[ 'files' ] );
        $firstSid  = $firstFile[ 'segments' ][ 0 ][ 'sid' ];

        if ( isset( $lastFile[ 'segments' ] ) and is_array( $lastFile[ 'segments' ] ) ) {
            $lastSegment = end( $lastFile[ 'segments' ] );
            $lastSid     = $lastSegment[ 'sid' ];

            $segment_translation_events = ( new TranslationEventDao() )->getLatestEventsInSegmentInterval(
                    $chunk->id, $firstSid, $lastSid );

            $by_id_segment = [];
            foreach ( $segment_translation_events as $record ) {
                $by_id_segment[ $record->id_segment ] = $record;
            }

            foreach ( $data[ 'files' ] as $file => $content ) {
                foreach ( $content[ 'segments' ] as $key => $segment ) {

                    if ( isset( $by_id_segment[ $segment[ 'sid' ] ] ) ) {
                        $data [ 'files' ] [ $file ] [ 'segments' ] [ $key ] [ 'revision_number' ] = ReviewUtils::sourcePageToRevisionNumber(
                                $by_id_segment[ $segment[ 'sid' ] ]->source_page
                        );
                    }

                }
            }
        }

        return $data;
    }

    /**
     * @param $projectFeatures
     * @param $controller NewController|createProjectController
     *
     * @return mixed
     * @throws Exception
     */
    public function filterCreateProjectFeatures( $projectFeatures, $controller ) {
        $projectFeatures[ self::FEATURE_CODE ] = new BasicFeatureStruct( [ 'feature_code' => self::FEATURE_CODE ] );

        return $controller->getFeatureSet()->filter( 'filterOverrideReviewExtended', $projectFeatures, $controller );
    }

}