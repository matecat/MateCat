<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Chunks_ChunkDao;
use Chunks_ChunkStruct;
use Exception;
use Features\ISegmentTranslationModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use Features\TranslationVersions\Model\SegmentTranslationEventStruct;
use SegmentTranslationChangeVector;
use TransactionableTrait;

class SegmentTranslationModel  implements  ISegmentTranslationModel {

    use TransactionableTrait ;

    /**
     * @var SegmentTranslationChangeVector
     */
    protected $model;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    protected $affectedChunkReviews = [] ;

    public function __construct( SegmentTranslationChangeVector $model ) {
        $this->model = $model;
        $this->chunk = Chunks_ChunkDao::getBySegmentTranslation( $this->model->getTranslation() );
    }

    public function evaluateReviewedWordsTransition() {
        $this->openTransaction() ;

        $sourcePageSpan = $this->model->sourcePagesSpan() ;
        if ( empty( $sourcePageSpan ) ) {
            return ;
        }
        // load all relevant source page ChunkReviewRecords
        // find all chunk reviews between source and destination and sort them by direction
        $chunkReviews = ( new ChunkReviewDao() )->findChunkReviewsInSourcePages( [ [
                $this->chunk->id, $this->chunk->password ] ],
                $sourcePageSpan  );

        if ( $this->model->getSourcePageDirection() === -1 )  {
            $chunkReviews = array_reverse( $chunkReviews ) ;
        }

        // find final revisions for segment
        $finalRevisions = ( new SegmentTranslationEventDao())->getFinalRevisionsForSegment(
                $this->chunk->id, $this->model->getSegmentStruct()->id
        );
        $sourcePagesWithFinalRevisions = array_map( function( SegmentTranslationEventStruct $event ) {
            return $event->source_page ;
        }, $finalRevisions );

        /**
         * here we decide how to move around revised_words and words for advancement.
         * Order of chunk reviews is based on direction. So if we are moving up the first is the lowest,
         * Otherwise the first is the upper. In any case the first chunk review record is the current revision stage.
         *
         * We have $finalRevisions array to know if the segment was ever assigned revised_words in that chunk.
         *
         * Possible conditions:
         *
         * 1. we are moving up entering a reviewed state.
         * 2. we are moving up from a reviewed state to another reviewed state
         *
         * 3. we are moving down exiting a reviwed state
         * 4. we are moving down from a reviewed state to another
         *
         * 5. we are not changing the reviwed state.
         * 6. we are not changing the translated state.
         *
         */

        $modifiedChunkReviewsToSave = [] ;
        $unsetFinalRevision         = [] ;
        $setFinalRevision           = [] ;
        $originSourcePage           = $this->model->getEventModel()->getOriginSourcePage();
        $destinationSourcePage      = $this->model->getEventModel()->getDestinationSourcePage() ;


        foreach( $chunkReviews as $chunkReview ) {
            if ( $this->model->isEnteringReviewedState() && $destinationSourcePage == $chunkReview->source_page ) {
                // expect the first chunk review record to be the final
                // add revised words and advancement
                $chunkReview->reviewed_words_count += $this->rawWordsCountWithPropagation();
                $chunkReview->advancement_wc       += $this->equivalentWordsCountWithPropagation();
                $setFinalRevision []                = $chunkReview->source_page ;
                $modifiedChunkReviewsToSave[]       = $chunkReview ;
                break;
            }

            elseif ( $this->model->isExitingReviewedState() ) {
                // expect the direction to be downwards from R3 -> R2 -> R1 etc.
                if ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    $unsetFinalRevision []              = $chunkReview->source_page ;
                }

                // expect advancement to be removed only from the current source page
                if ( $chunkReview->source_page == $originSourcePage ) {
                    $chunkReview->advancement_wc -= $this->equivalentWordsCountWithPropagation();
                }

                $modifiedChunkReviewsToSave[] = $chunkReview ;
            }

            // TODO: in the following two cases we shuold considere if the segment is changed or not.
            elseif ( $this->model->isBeingLowerReviewed() ) {
                // whenever a revision is lower reviewed we expect the upper revisions to be invalidated.
                // the reviewed words count is removed from the upper one and moved to the lower one.
                if ( $originSourcePage == $chunkReview->source_page ) {
                    $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    $unsetFinalRevision[]               = $chunkReview->source_page ;
                    // expect advancement to be assigned to the origin source_page
                    $chunkReview->advancement_wc       -= $this->equivalentWordsCountWithPropagation();
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;

                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    // TODO: evaluate the case in which the destination revision never received a revision before
                    // evaluate $sourcePagesWithFinalRevisions
                    if ( !in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                        $chunkReview->reviewed_words_count += $this->rawWordsCountWithPropagation();
                        $setFinalRevision[]                 = $chunkReview->source_page ;
                    }
                    $chunkReview->advancement_wc       += $this->equivalentWordsCountWithPropagation();
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;

                } elseif ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation() ;
                    $unsetFinalRevision[]               = $chunkReview->source_page ;
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;
                }
            }

            elseif ( $this->model->isBeingUpperReviewed() ) {
                if ( $originSourcePage == $chunkReview->source_page ) {
                    // TODO: decide wether or not to remove the revised words
                    // $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    // expect advancement to be assigned to the origin source_page
                    $chunkReview->advancement_wc -= $this->equivalentWordsCountWithPropagation();
                    $modifiedChunkReviewsToSave[] = $chunkReview ;

                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    $chunkReview->reviewed_words_count += $this->rawWordsCountWithPropagation();
                    $setFinalRevision[]                 = $chunkReview->source_page ;
                    $chunkReview->advancement_wc       += $this->equivalentWordsCountWithPropagation();
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;

                } elseif ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    // in case of upper revisions this case should never happen because latest state is always
                    // the current revision state so it's not possible to move from R1 to R3 if an R2 is current
                    // state.
                    false ;
                }
            }
        }

        foreach( $modifiedChunkReviewsToSave as $chunkReview ) {
            $chunkReviewModel = new ChunkReviewModel( $chunkReview ) ;
            $chunkReviewModel->updatePassFailResult() ;
        }

        $this->updateFinalRevisionFlag( $unsetFinalRevision );
        $this->commitTransaction();
    }

    protected function equivalentWordsCountWithPropagation() {
        return $this->getWordCountWithPropagation(
                $this->model->getOldTranslation()->eq_word_count
        ) ;
    }

    protected function rawWordsCountWithPropagation() {
        return $this->getWordCountWithPropagation(
                $this->model->getSegmentStruct()->raw_word_count
        ) ;
    }

    /**
     * Unset the final_revision flag from any revision we removed reviwed_words.
     * Apply final_revision flag to the current event.
     *
     * @param $unsetFinalRevision
     */
    protected function updateFinalRevisionFlag( $unsetFinalRevision ) {

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->chunk->id,
                    $this->model->getSegmentStruct()->id,
                   $unsetFinalRevision
            ) ;
        }

        $eventStruct = $this->model->getEventModel()->getCurrentEvent() ;
        $eventStruct->final_revision = (int) $eventStruct->source_page > 1 ;
        SegmentTranslationEventDao::updateStruct( $eventStruct, ['fields' => ['final_revision'] ] ) ;

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