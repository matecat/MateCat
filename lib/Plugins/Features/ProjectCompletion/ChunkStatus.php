<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/01/2017
 * Time: 17:11
 */

namespace Features\ProjectCompletion;


class ChunkStatus
{

    protected $chunk ;

    public function __construct( \Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;
    }

    public function isCompletable()
    {
        // TODO: Implement isCompletable() method.
    }

    public function isReviewable()
    {
        // TODO: Implement isReviewable() method.
    }

    public function isTranslatable()
    {
        // TODO: Implement isTranslatable() method.
    }

}