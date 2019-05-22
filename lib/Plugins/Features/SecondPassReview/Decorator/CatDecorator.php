<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 13:09
 */

namespace Features\SecondPassReview\Decorator ;
use AbstractCatDecorator;
use LQA\ChunkReviewDao;

class CatDecorator extends AbstractCatDecorator {

    public function decorate() {
        $secondRevisions = ChunkReviewDao::findSecondRevisionsChunkReviewsByChunkIds( [ [
                        $this->controller->getChunk()->id,
                        $this->controller->getChunk()->password
                ] ]  ) ;

        $this->template->secondRevisionsCount = count( $secondRevisions );
    }

}