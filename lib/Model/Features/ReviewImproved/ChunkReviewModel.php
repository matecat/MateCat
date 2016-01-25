<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/25/16
 * Time: 3:28 PM
 */

namespace Features\ReviewImproved;


class ChunkReviewModel
{
    /**
     * @var \LQA\ChunkReviewStruct
     */
    private $chunk_review;


    private $score;


    public function __construct( \LQA\ChunkReviewStruct $chunk_review ) {
        $this->chunk_review = $chunk_review ;
        $this->score = $this->chunk_review->score ;
    }

    public function addWordsCount( $count ) {
        $this->chunk_review->reviewed_words_count += $count ;
        $this->reviewedWordsCountDidChange() ;
    }

    public function subtractWordsCount( $count ) {
        $this->chunk_review->reviewed_words_count -= $count ;
        $this->reviewedWordsCountDidChange() ;
    }

    private function reviewedWordsCountDidChange() {
        $score_per_mille =  $this->chunk_review->score /
            $this->chunk_review->reviewed_words_count * 1000 ;

        $project = \Projects_ProjectDao::findById( $this->chunk_review->id_project );
        $lqa_model = $project->getLqaModel();

        $this->chunk_review->is_pass = ( $score_per_mille <= $lqa_model->getLimit() ) ;

        \LQA\ChunkReviewDao::updateStruct( $this->chunk_review, array(
            'fields' => array('reviewed_words_count', 'is_pass')));
    }

}