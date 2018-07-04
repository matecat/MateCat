<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/02/2017
 * Time: 15:29
 */

namespace Features\ReviewImproved;

use LQA\ChunkReviewDao;

class Utils {

    public static function revisePassword( \Chunks_ChunkStruct $chunk ) {
        if ( $chunk->getProject()-> isFeatureEnabled(\Features::REVIEW_IMPROVED) ) {
            $review_record = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword($chunk->id, $chunk->password) ;
            return $review_record->review_password ;
        }
        return $chunk->password ;
    }

}