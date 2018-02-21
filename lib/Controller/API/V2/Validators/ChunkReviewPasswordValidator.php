<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2018
 * Time: 15:35
 */

namespace API\V2\Validators;


use API\V2\Exceptions\NotFoundException;
use LQA\ChunkReviewDao;

class ChunkReviewPasswordValidator extends ChunkPasswordValidator {

    protected $chunk_review ;

    public function _validate() {

        $this->chunk_review = ChunkReviewDao::findByReviewPasswordAndJobId(
                $this->request->password, $this->request->id_job ) ;

        if ( ! $this->chunk_review ) {
            throw new NotFoundException('Not Found', 404 );
        }


        $this->chunk = $this->chunk_review->getChunk() ;
    }
}