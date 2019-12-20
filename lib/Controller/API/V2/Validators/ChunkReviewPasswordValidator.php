<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2018
 * Time: 15:35
 */

namespace API\V2\Validators;


use API\V2\Exceptions\NotFoundException;
use Features\ReviewExtended\ReviewUtils;
use LQA\ChunkReviewDao;

class ChunkReviewPasswordValidator extends ChunkPasswordValidator {

    protected $chunk_review ;

    public function _validate() {

        if($this->revision_number > 1){
            $this->chunk_review = ( new \LQA\ChunkReviewDao() )->findByJobIdReviewPasswordAndSourcePage(
                    $this->id_job,
                    $this->password,
                    ReviewUtils::revisionNumberToSourcePage( $this->revision_number )
            );

            if ( ! $this->chunk_review ) {
                throw new NotFoundException('Not Found', 404 );
            }

            $this->chunk = $this->chunk_review->getChunk() ;
        } else {
            throw new NotFoundException('Not Found', 404 );
        }
    }
}