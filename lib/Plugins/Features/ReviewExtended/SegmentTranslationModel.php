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
    protected $_model;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $_chunk;

    protected $affectedChunkReviews        = [];
    private   $finalRevisionsForPropagated = [];
    /**
     * @var ChunkReviewStruct[]
     */
    protected   $_chunkReviews;

    public function __construct( SegmentTranslationChangeVector $model, array $chunkReviews ) {
        $this->_model        = $model;
        $this->_chunkReviews = $chunkReviews ;
        $this->_chunk        = $model->getChunk();
    }

    public function evaluateChunkReviewTransition() {
        $this->openTransaction() ;

        $finalRevisions = ( new SegmentTranslationEventDao())->getFinalRevisionsForSegment(
                $this->_chunk->id, $this->_model->getSegmentStruct()->id
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

        $unsetFinalRevision         = [] ;
        $originSourcePage           = $this->_model->getEventModel()->getOriginSourcePage();
        $destinationSourcePage      = $this->_model->getEventModel()->getDestinationSourcePage() ;

        // popuplate structs for current segment and propagations
        foreach( $this->_chunkReviews as $chunkReview ) {

            if ( $this->_model->isEnteringReviewedState() && $destinationSourcePage == $chunkReview->source_page ) {
                // expect the first chunk review record to be the final
                // add revised words and advancement
                $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count ;
                $chunkReview->penalty_points       += $this->getPenaltyPointsForSourcePage( $chunkReview->source_page );
                $chunkReview->total_tte            += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit;
                $chunkReview->advancement_wc       += $this->advancementWordCount();
                break;
            }

            elseif ( $this->_model->isExitingReviewedState() ) {
                // expect the direction to be downwards from R3 -> R2 -> R1 etc.
                if ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    $chunkReview->reviewed_words_count -= $this->_model->getSegmentStruct()->raw_word_count ;
                    $chunkReview->penalty_points       -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page ) ;
                    // $chunkReview->advancement_wc       -= $this->advancementWordCount() ;
                    $unsetFinalRevision []              = $chunkReview->source_page ;
                }

                if ( $chunkReview->source_page == $originSourcePage ) {
                    $chunkReview->advancement_wc -= $this->advancementWordCount() ;
                }

            }

            // TODO: in the following two cases we shuold considere if the segment is changed or not.
            elseif ( $this->_model->isBeingLowerReviewed() ) {
                // whenever a revision is lower reviewed we expect the upper revisions to be invalidated.
                // the reviewed words count is removed from the upper one and moved to the lower one.
                if ( $originSourcePage == $chunkReview->source_page ) {
                    $chunkReview->reviewed_words_count -= $this->_model->getSegmentStruct()->raw_word_count ;
                    $chunkReview->penalty_points       -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page ) ;
                    $chunkReview->advancement_wc       -= $this->advancementWordCount() ;
                    $unsetFinalRevision[]               = $chunkReview->source_page ;


                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    // TODO: evaluate the case in which the destination revision never received a revision before
                    // evaluate $sourcePagesWithFinalRevisions
                    if ( !in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                        $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count;
                        $chunkReview->penalty_points       += $this->getPenaltyPointsForSourcePage( $chunkReview->source_page ) ;
                    }

                    $chunkReview->advancement_wc       += $this->advancementWordCount() ;
                    $chunkReview->total_tte            += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit ;

                } elseif ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    $chunkReview->reviewed_words_count -= $this->_model->getSegmentStruct()->raw_word_count ;
                    $chunkReview->penalty_points       -= $this->getPenaltyPointsForSourcePage($chunkReview->source_page) ;
                    $unsetFinalRevision[]               = $chunkReview->source_page ;
                }

            }

            elseif ( $this->_model->isBeingUpperReviewed() ) {
                if ( $originSourcePage == $chunkReview->source_page ) {
                    // TODO: decide wether or not to remove the revised words
                    // $chunkReview->reviewed_words_count -= $this->rawWordsCountWithPropagation();
                    // expect advancement to be assigned to the origin source_page
                    $chunkReview->advancement_wc  -= $this->advancementWordCount() ;


                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count;
                    $chunkReview->penalty_points       += $this->getPenaltyPointsForSourcePage($chunkReview->source_page ) ;
                    $chunkReview->advancement_wc       += $this->advancementWordCount() ;
                    $chunkReview->total_tte            += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit ;

                } elseif ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    // in case of upper revisions this case should never happen because latest state is always
                    // the current revision state so it's not possible to move from R1 to R3 if an R2 is current
                    // state.
                    false ;
                }
            }
            elseif (
                    $this->_model->isModifyingICE() &&
                    $this->_model->getEventModel()->getDestinationSourcePage() == Constants::SOURCE_PAGE_TRANSLATE ) {
                /**
                 * Enter this condition when we are just changing source page. This change only affects advancement wc.
                 * This is the case of ICE matches moving from R1 to TR.
                 */
                $chunkReview->advancement_wc -= $this->advancementWordCount() ;
            }
            elseif ( $this->_model->isEditingCurrentRevision() && $destinationSourcePage == $chunkReview->source_page ) {
                $chunkReview->total_tte += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit ;
            }
        }

        $this->updateFinalRevisionFlag( $unsetFinalRevision );

        // XXX TODO REfactor remove this recount
        foreach( $this->_chunkReviews as $chunkReview ) {
            $chunkReviewModel = new ChunkReviewModel( $chunkReview ) ;
            $chunkReviewModel->updatePassFailResult() ;
        }

        $this->commitTransaction();

        // Send email
        if ( $this->_model->getEventModel()->isPropagationSource() && $this->_model->isBeingLowerReviewed() ) {
            $chunkReviewsWithFinalRevisions = [] ;
            foreach ( $this->_chunkReviews as $chunkReview ) {
                if ( in_array( $chunkReview->source_page, $sourcePagesWithFinalRevisions ) ) {
                    $chunkReviewsWithFinalRevisions[ $chunkReview->source_page ] = $chunkReview ;
                }
            }

            $this->_sendNotificationEmail( $finalRevisions, $chunkReviewsWithFinalRevisions );
        }

    }

    protected function _sendNotificationEmail($finalRevisions, $chunkReviewsWithFinalRevisions) {
        $emails = [];
        $userWhoChangedTheSegment = $this->_model->getEventUser() ;

        foreach( $finalRevisions as $finalRevision ) {
            if ( $finalRevision->source_page != $this->_model->getEventModel()->getDestinationSourcePage() ) {
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
            $url = Routes::revise( $this->_chunk->getProject()->name, $revision->id_job, $revision->review_password,
                    $this->_chunk->source, $this->_chunk->target, [
                            'revision_number' => Utils::sourcePageToRevisionNumber( $revision->source_page ),
                            'id_segment'      => $this->_model->getSegmentStruct()->id
                    ] ) ;

            $delivery = new RevisionChangedNotificationEmail( $email['recipient'], $url, $userWhoChangedTheSegment ) ;
            $delivery->send();
        }
    }


    protected function advancementWordCount() {
        if ( $this->_model->getEventModel()->getOldTranslation()->isICE() ) {
            $wc = $this->_model->getSegmentStruct()->raw_word_count ;
        }
        else {
            $wc = $this->_model->getOldTranslation()->eq_word_count ;
        }
        return $wc ;
    }

    protected function getPenaltyPointsForSourcePage( $source_page) {
        return ( new ChunkReviewDao() )
                ->getPenaltyPointsForChunkAndSourcePageAndSegment( $this->_chunk, [ $this->_model->getSegmentStruct()->id ], $source_page ) ;
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

    //XXXX
    protected function updateFinalRevisionFlag( $unsetFinalRevision ) {
        $eventStruct = $this->_model->getEventModel()->getCurrentEvent();
        $is_revision = (int) $eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE  ;

        if ( $is_revision ) {
           $unsetFinalRevision = array_merge( $unsetFinalRevision, [ $eventStruct->source_page ] )  ;
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->_chunk->id, [ $this->_model->getSegmentStruct()->id ] , $unsetFinalRevision
            ) ;
        }

        $eventStruct->final_revision = $is_revision ;
        SegmentTranslationEventDao::updateStruct( $eventStruct, ['fields' => ['final_revision'] ] ) ;
    }

}