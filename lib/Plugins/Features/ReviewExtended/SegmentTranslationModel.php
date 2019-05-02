<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Chunks_ChunkDao;
use Features\ISegmentTranslationModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use SegmentTranslationChangeVector;
use TransactionableTrait;

use Features\SecondPassReview\Model\SegmentTranslationEventDao ;

class SegmentTranslationModel  implements  ISegmentTranslationModel {

    use TransactionableTrait ;

    /**
     * @var SegmentTranslationChangeVector
     */
    protected $model;

    /**
     * @var \Chunks_ChunkStruct
     */
    protected $chunk;

    protected $affectedChunkReviews = [] ;

    public function __construct( SegmentTranslationChangeVector $model ) {

        $this->model = $model;
        $this->chunk = Chunks_ChunkDao::getBySegmentTranslation( $this->model->getTranslation() );
    }

    public function evaluateReviewedWordsTransition() {
        $this->openTransaction() ;

        /**
         * we need to check the transition in regards of second pass
         *
         * possible cases are:
         *
         * 1. This event is reviweing a segment for the first time
         * 2. This event is updating an existing review
         * 3. This event is making a change to a reviewed segment
         *
         * If the segment is being reviewed, we need to find the relevant chunk_review record to
         * update with words count.
         *
         e If the segment is being changed and one or more upper reviews were already done, we need
         * to find the relevant chunk_review records and subtract the reviewed words count from there.
         *
         * In order to do this we need to find the initial an destination source pages.
         *
         */
        if (
                $this->model->isEnteringReviewedState() ||
                $this->model->isBeingUpperReviewed()
        ) {

            $this->addCount();

        } elseif (
                $this->model->isExitingReviewedState() ||
                $this->model->isBeingLowerReviewed()
        ) {

            $this->subtractCount();
        }

        // update final revision flag
        $this->updateFinalRevisionFlag() ;

        $this->commitTransaction();
    }

    /**
     * This sets the `final_revision` flag checking if the event generated a Revision
     * movement upward, backward or the Revision remained the same.
     *
     * If `getRollbackRevisionsSpan` retuns an empty array, the movement was upward or neutral. In that case
     * we unset the `final_revision` flag on all previous `segment_translation_events` records of the same
     * `source_page`.
     *
     * If `getRollbackRevisionsSpan` returns is not empty we remove the flag from all the upper revisions.
     *
     * Then we set the `final_revision` flag to 1 for the current event, only if the event is actually a revision.
     *
     */
    protected function updateFinalRevisionFlag() {
        $rollbackRevisionSpan = $this->model->getRollbackRevisionsSpan() ;

        if ( empty( $rollbackRevisionSpan ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->chunk->id,
                    $this->model->getSegmentStruct()->id,
                    [ $this->model->getDestinationSourcePage() ]
            ) ;
        }
        else {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->chunk->id,
                    $this->model->getSegmentStruct()->id,
                    $rollbackRevisionSpan
            ) ;
        }

        $eventStruct = $this->model->getEventModel()->getCurrentEvent() ;
        $eventStruct->final_revision = (int) $eventStruct->source_page > 1 ;

        SegmentTranslationEventDao::updateStruct( $eventStruct, ['fields' => ['final_revision'] ] ) ;
    }

    protected function addCount() {
        $this->affectedChunkReviews = ( new ChunkReviewDao() )->findChunkReviewsByChunkIds([
            [ $this->chunk->id, $this->chunk->password ]
        ], $this->model->getDestinationSourcePage() );

        $model   = new ChunkReviewModel( $this->affectedChunkReviews[ 0 ] );

        $model->addWordsCount( $this->getWordCountWithPropagation(
                $this->model->getSegmentStruct()->raw_word_count
        ) );
    }

    protected function subtractCount() {
        $rollbackRevisionSpan = $this->model->getRollbackRevisionsSpan() ;
        $segment              = $this->model->getSegmentStruct();

        foreach( $rollbackRevisionSpan as $sourcePage ) {
            $chunkReview = ( new ChunkReviewDao() )->findChunkReviewsByChunkIds( [
                    [ $this->chunk->id, $this->chunk->password ]
            ], $sourcePage );

            $this->affectedChunkReviews[] = $chunkReview[ 0 ]  ;

            $model = new ChunkReviewModel( $chunkReview[ 0 ] );
            $model->subtractWordsCount( $this->getWordCountWithPropagation( $segment->raw_word_count ) );
        }
    }

    protected function getWordCountWithPropagation( $count ) {
        if ( $this->model->didPropagate() ) {
            return $count + ( $count * count( $this->model->getPropagatedIds() ) ) ;
        }
        else {
            return $count ;
        }
    }

}