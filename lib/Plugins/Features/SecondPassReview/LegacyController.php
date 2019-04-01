<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:33
 */

namespace Features\SecondPassReview;

use API\V2\Exceptions\NotFoundException;
use LQA\ChunkReviewDao;

class LegacyController {
    public static function beginDoAction( $controller ) {
        if ( strpos(get_class( $controller ), 'catController' ) !== false ) {
            /** @var \catController $controller */
            if ( !$controller->isRevision() ) {
                return ;
            }

            if ( $controller->getRevisionNumber() > 1 ) {
                $chunk_review = ( new ChunkReviewDao())->findByReviewPasswordAndJobIdAndSourcePage(
                        $controller->getReviewPassword(), $controller->getChunk()->id,
                        Utils::revisionNumberToSourcePage( $controller->getRevisionNumber() )
                );

                if ( !$chunk_review ) {
                    throw  new NotFoundException("This revision did not start yet: " . $controller->getRevisionNumber() ) ;
                }
            }
        }
    }

}