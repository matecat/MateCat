<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:26
 */

namespace Features;

use catController;
use Exceptions\NotFoundException;
use Features;
use Features\SecondPassReview\Utils;
use Klein\Klein;
use LQA\ChunkReviewDao;

class SecondPassReview extends BaseFeature {
    const FEATURE_CODE = 'second_pass_review' ;

    protected static $dependencies = [
            Features::REVIEW_EXTENDED
    ];

    public static function loadRoutes( Klein $klein ) {
        route( '/project/[:id_project]/[:password]/reviews', 'POST',
                'Features\SecondPassReview\Controller\ReviewsController', 'createReview' );
    }

    public function catControllerChunkFound( catController $controller ) {
        if ( !$controller->isRevision() ) {
            return ;
        }

        if ( $controller->getRevisionNumber() > 1 ) {
            $chunk_review = ( new ChunkReviewDao() )->findByJobIdPasswordAndSourcePage(
                    $controller->getChunk()->id,
                    $controller->getChunk()->password,
                    Utils::revisionNumberToSourcePage( $controller->getRevisionNumber() )
            );

            if ( empty( $chunk_review ) ) {
                throw new NotFoundException("This revision did not start yet: " . $controller->getRevisionNumber() ) ;
            }
        }
    }

    public function filterSourcePage( $sourcePage ) {
        $_from_url = parse_url( @$_SERVER['HTTP_REFERER'] );
        $matches = null ;
        preg_match( '/revise([2-9])?\//s' , $_from_url['path'], $matches ) ;
        if  ( count( $matches ) > 1 ) {
            $sourcePage = Utils::revisionNumberToSourcePage( $matches[ 1 ] ) ;
        }
        return $sourcePage ;
    }
}