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
use Constants;
use Exception;
use Features\ISegmentTranslationModel;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\SecondPassReview\Email\RevisionChangedNotificationEmail;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use Features\SecondPassReview\Utils;
use Features\TranslationVersions\Model\SegmentTranslationEventStruct;
use LQA\ChunkReviewStruct;
use Routes;
use SegmentTranslationChangeVector;
use TransactionableTrait;
use Users_UserDao;

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

        $segmentIdsForPoints = array_unique( array_merge( [ $this->model->getSegmentStruct()->id ], $this->model->getPropagatedIds() ) ) ;
        $segmentPointsBySourcePage  = ( new ChunkReviewDao() )
                ->getPenaltyPointsForChunkAndSourcePageAndSegment( $this->chunk, $segmentIdsForPoints ) ;

        foreach( $chunkReviews as $chunkReview ) {

            if ( $this->model->isEnteringReviewedState() && $destinationSourcePage == $chunkReview->source_page ) {
                // expect the first chunk review record to be the final
                // add revised words and advancement
                $chunkReview->reviewed_words_count += $this->rawWordsCountWithPropagation();
                $chunkReview->penalty_points       += $segmentPointsBySourcePage[ $chunkReview->source_page ]  ;
                $chunkReview->advancement_wc       += $this->advancementWordCountWithPropagation();
                $setFinalRevision []                = $chunkReview->source_page ;
                $modifiedChunkReviewsToSave[]       = $chunkReview ;
                break;
            }

            elseif ( $this->model->isExitingReviewedState() ) {
                // expect the direction to be downwards from R3 -> R2 -> R1 etc.
                if ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    $chunkReview->penalty_points       -= $segmentPointsBySourcePage[ $chunkReview->source_page ] ;
                    $unsetFinalRevision []              = $chunkReview->source_page ;
                }

                // expect advancement to be removed only from the current source page
                if ( $chunkReview->source_page == $originSourcePage ) {
                    $chunkReview->advancement_wc -= $this->advancementWordCountWithPropagation();
                }

                $modifiedChunkReviewsToSave[] = $chunkReview ;
            }

            // TODO: in the following two cases we shuold considere if the segment is changed or not.
            elseif ( $this->model->isBeingLowerReviewed() ) {
                // whenever a revision is lower reviewed we expect the upper revisions to be invalidated.
                // the reviewed words count is removed from the upper one and moved to the lower one.
                if ( $originSourcePage == $chunkReview->source_page ) {
                    $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    $chunkReview->penalty_points       -= $segmentPointsBySourcePage[ $chunkReview->source_page ] ;
                    $unsetFinalRevision[]               = $chunkReview->source_page ;

                    // expect advancement to be assigned to the origin source_page
                    $chunkReview->advancement_wc       -= $this->advancementWordCountWithPropagation();
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;

                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    // TODO: evaluate the case in which the destination revision never received a revision before
                    // evaluate $sourcePagesWithFinalRevisions
                    if ( !in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                        $chunkReview->reviewed_words_count += $this->rawWordsCountWithPropagation();
                        $chunkReview->penalty_points       += $segmentPointsBySourcePage[ $chunkReview->source_page ]  ;
                        $setFinalRevision[]                 = $chunkReview->source_page ;
                    }
                    $chunkReview->advancement_wc       += $this->advancementWordCountWithPropagation();
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;

                } elseif ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation() ;
                    $chunkReview->penalty_points       -= $segmentPointsBySourcePage[ $chunkReview->source_page ] ;
                    $unsetFinalRevision[]               = $chunkReview->source_page ;
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;
                }
            }

            elseif ( $this->model->isBeingUpperReviewed() ) {
                if ( $originSourcePage == $chunkReview->source_page ) {
                    // TODO: decide wether or not to remove the revised words
                    // $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    // expect advancement to be assigned to the origin source_page
                    $chunkReview->advancement_wc -= $this->advancementWordCountWithPropagation();
                    $modifiedChunkReviewsToSave[] = $chunkReview ;

                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    $chunkReview->reviewed_words_count += $this->rawWordsCountWithPropagation();
                    $chunkReview->penalty_points       += $segmentPointsBySourcePage[ $chunkReview->source_page ]  ;

                    $setFinalRevision[]                 = $chunkReview->source_page ;
                    $chunkReview->advancement_wc       += $this->advancementWordCountWithPropagation();
                    $modifiedChunkReviewsToSave[]       = $chunkReview ;

                } elseif ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    // in case of upper revisions this case should never happen because latest state is always
                    // the current revision state so it's not possible to move from R1 to R3 if an R2 is current
                    // state.
                    false ;
                }
            }
            else {
                // TODO
            }
        }

        foreach( $modifiedChunkReviewsToSave as $chunkReview ) {
            $chunkReviewModel = new ChunkReviewModel( $chunkReview ) ;
            $chunkReviewModel->updatePassFailResult() ;
        }

        $this->updateFinalRevisionFlag( $unsetFinalRevision );
        $this->commitTransaction();

        if ( $this->model->isBeingLowerReviewed() ) {
            $chunkReviewsWithFinalRevisions = [] ;
            foreach ( $chunkReviews as $chunkReview ) {
                if ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    $chunkReviewsWithFinalRevisions[ $chunkReview->source_page ] = $chunkReview ;
                }
            }

            $this->_sendNotificationEmail( $finalRevisions, $chunkReviewsWithFinalRevisions );
        }

    }

    protected function _sendNotificationEmail($finalRevisions, $chunkReviewsWithFinalRevisions) {
        $emails = [];
        $userWhoChangedTheSegment = $this->model->getEventUser() ;

        foreach( $finalRevisions as $finalRevision ) {
            if ( $finalRevision->source_page != $this->model->getEventModel()->getDestinationSourcePage() ) {
                $user = ( new Users_UserDao() )->getByUid( $finalRevision->uid );
                if ( $user ) {
                    $emails[] = [
                            'recipient' => $user,
                            'revision'  => $chunkReviewsWithFinalRevisions[ $finalRevision->source_page ]
                    ] ;
                }
            }
        }

        foreach( $emails as $email ) {
            if ( !is_null($userWhoChangedTheSegment) && $userWhoChangedTheSegment->email == $email['recipient']->email ) {
                continue ;
            }

            /** @var ChunkReviewStruct $revision */
            $revision = $email['revision'] ;
            $url = Routes::revise( $this->chunk->getProject()->name, $revision->id_job, $revision->review_password,
                    $this->chunk->source, $this->chunk->target, [
                            'revision_number' => Utils::sourcePageToRevisionNumber( $revision->source_page ),
                            'id_segment'      => $this->model->getSegmentStruct()->id
                    ] ) ;

            $delivery = new RevisionChangedNotificationEmail( $email['recipient'], $url, $userWhoChangedTheSegment ) ;
            $delivery->send();
        }
    }

    /**
     * Words for advancement are raw for ICE, equivalent otherwise.
     *
     * @return int
     */
    protected function advancementWordCountWithPropagation() {
        if ( $this->model->getEventModel()->getOldTranslation()->isICE() ) {
            $wc = $this->model->getSegmentStruct()->raw_word_count ;
        }
        else {
            $wc = $this->model->getOldTranslation()->eq_word_count ;
        }
        return $this->getWordCountWithPropagation( $wc );
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
     * If the current event is a revision, ensure the source_page is included in the
     * unset list.
     *
     * @param $unsetFinalRevision
     */
    protected function updateFinalRevisionFlag( $unsetFinalRevision ) {
        $eventStruct = $this->model->getEventModel()->getCurrentEvent();
        $is_revision = (int) $eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE  ;

        if ( $is_revision ) {
           $unsetFinalRevision = array_merge( $unsetFinalRevision, [ $eventStruct->source_page ] )  ;
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->chunk->id,
                    $this->model->getSegmentStruct()->id,
                   $unsetFinalRevision
            ) ;
        }

        $eventStruct->final_revision = $is_revision ;
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