<?php

namespace Features\ReviewImproved\Observer;


use Features\ReviewImproved;

class SegmentTranslationObserver implements \SplObserver {

    /**
     * @var \SegmentTranslationModel
     */
    private $subject;

    public function update( \SplSubject $segment_translation_model ) {
        $this->subject = $segment_translation_model ;

        $model = new ReviewImproved\SegmentTranslationModel( $segment_translation_model );
        $model->addOrSubtractCachedReviewedWordsCount();

        // we need to recount score globally because of autopropagation.
        $model->recountPenaltyPoints();
    }
}