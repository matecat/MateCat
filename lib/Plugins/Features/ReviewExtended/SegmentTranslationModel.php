<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
use Constants;
use Exception;
use Features\SecondPassReview\Email\RevisionChangedNotificationEmail;
use Features\SecondPassReview\Model\TranslationEventDao;
use Features\TranslationVersions\Model\TranslationEvent;
use Features\TranslationVersions\Model\TranslationEventStruct;
use LQA\ChunkReviewStruct;
use LQA\EntryCommentStruct;
use LQA\EntryDao;
use LQA\EntryStruct;
use LQA\EntryWithCategoryStruct;
use Routes;
use TransactionableTrait;
use Users_UserDao;
use WordCount\CounterModel;

class SegmentTranslationModel implements ISegmentTranslationModel {

    use TransactionableTrait;

    /**
     * @var TranslationEvent
     */
    protected $_event;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $_chunk;

    /**
     * @var \Projects_ProjectStruct
     */
    protected $_project;

    /**
     * @var ChunkReviewStruct[]
     */
    protected $_chunkReviews;

    /**
     * @var array
     */
    protected $_issuesDeletionList = [];

    /**
     * @var array
     */
    protected $_sourcePagesWithFinalRevisions;

    /**
     * @var array
     */
    private $_finalRevisions;
    /**
     * @var CounterModel
     */
    private $_jobWordCounter;

    public function __construct( TranslationEvent $model, CounterModel $jobWordCounter, array $chunkReviews ) {
        $this->_event          = $model;
        $this->_chunkReviews   = $chunkReviews;
        $this->_chunk          = $model->getChunk();
        $this->_project        = $this->_chunk->getProject();
        $this->_jobWordCounter = $jobWordCounter;

        $this->_finalRevisions = ( new TranslationEventDao() )->getFinalRevisionsForSegment(
                $this->_chunk->id, $this->_event->getSegmentStruct()->id
        );

        $this->_sourcePagesWithFinalRevisions = array_map( function ( TranslationEventStruct $event ) {
            return $event->source_page;
        }, $this->_finalRevisions );
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return bool
     */
    private function aFinalRevisionExistsForThisChunk( ChunkReviewStruct $chunkReview ) {
        return in_array( $chunkReview->source_page, $this->_sourcePagesWithFinalRevisions );
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return void
     * @throws Exception
     */
    private function decreaseCountersAndRemoveIssues( ChunkReviewStruct $chunkReview ) {
        $chunkReview->reviewed_words_count -= $this->_event->getSegmentStruct()->raw_word_count;
        $chunkReview->penalty_points       -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page );

        // when downgrading a revision to translation the issues must be removed ( from R1, R2 or both )
        $this->flagIssuesToBeDeleted( $chunkReview->source_page );
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return void
     * @throws Exception
     */
    private function increaseAllCounters( ChunkReviewStruct $chunkReview ) {
        $chunkReview->reviewed_words_count += $this->_event->getSegmentStruct()->raw_word_count;
        $chunkReview->total_tte            += $this->_event->getCurrentEvent()->time_to_edit;
    }

    /**
     * Here we decide how to move around revised_words and words for advancement.
     * Order of chunk reviews is based on direction.
     *
     * We have $finalRevisions array to know if the segment was already assigned revised_words in that chunk.
     *
     * We are iterating on all the qa chunk reviews, and we can/must possibly check:
     *
     *  1. if the current chunk we are iterating is the one this event refers to:
     *          `$this->_event->currentEventIsOnThisChunk( $chunkReview )`
     *  2. if the current chunk we are iterating is the one on which the last action was performed:
     *          `$this->_event->lastEventWasOnThisChunk( $chunkReview )`
     *  3. if the current chunk we are iterating has a revision event attached ( this means that to the segment was already assigned revised_words in that chunk ):
     *          `$this->aFinalRevisionExistsForThisChunk( $chunkReview )`
     *
     *
     * On the current specific segment event we can/must possibly check:
     *
     * 1. The segment comes from a state of R(N) and is going to a state of R(N-1)
     *          `$this->_event->isBeingLowerReviewed()`
     * 2. The segment comes from a state of R(N) and is going to a state of Translated:
     *          `$this->_event->isDowngradedToTranslated()`
     * 3. The segment is in R(N) state, and is not performing any state transition:
     *          `$this->_event->isEditingCurrentRevision()`
     * 4. If the segment is an ICE/Pre-Translated we check if it is its first modification:
     *          `$this->_event->iceIsAboutToBeReviewedForTheFirstTime()`
     *
     * Possible conditions:
     *
     * 1. we are moving up entering a reviewed state.
     * 2. we are moving up from a reviewed state to another reviewed state
     *
     * 3. we are moving down exiting a reviewed state
     * 4. we are moving down from a reviewed state to another
     *
     * 5. we are not changing the reviewed state.
     * 6. we are not changing the translated state. ( Nothing happens )
     *
     * @return ChunkReviewTranslationEventTransition
     * @throws Exception
     */
    public function evaluateAndGetChunkReviewTranslationEventTransition() {

        $chunkReviews       = [];
        $unsetFinalRevision = [];

        // for debugging purposes
        $_previousEventSourcePage = $this->_event->getPreviousEventSourcePage();
        $_currentEventSourcePage  = $this->_event->getCurrentEventSourcePage();

        if ( $this->_event->isChangingStatus() ) {
            $this->_jobWordCounter->setOldStatus( $this->_event->getOldTranslation()->status );
            $this->_jobWordCounter->setNewStatus( $this->_event->getWantedTranslation()->status );
            $this->_jobWordCounter->setUpdatedValues( $this->_event->getOldTranslation()->eq_word_count, $this->_event->getSegmentStruct()->raw_word_count );
        }

        $segmentReviewTransitionModel = new ChunkReviewTranslationEventTransition( $this->_event );

        // populate structs for current segment and propagations
        // we are iterating on ALL the revision levels ( chunks )
        for ( $i = 0; $i < count( $this->_chunkReviews ); $i++ ) {

            // build a new ChunkReviewStruct
            $chunkReview              = new ChunkReviewStruct();
            $chunkReview->id          = $this->_chunkReviews[ $i ]->id;
            $chunkReview->id_project  = $this->_chunkReviews[ $i ]->id_project;
            $chunkReview->id_job      = $this->_chunkReviews[ $i ]->id_job;
            $chunkReview->password    = $this->_chunkReviews[ $i ]->password;
            $chunkReview->source_page = $this->_chunkReviews[ $i ]->source_page;

            // we are not taking in consideration the changes in the content
            if ( $this->_event->isBeingLowerReviewed() ) {

                // in this case we are handling the chunk ( state ) from which the segment comes
                if ( $this->_event->lastEventWasOnThisChunk( $chunkReview ) ) {

                    // whenever a revision is lower reviewed, we expect the upper revisions to be invalidated.
                    // the value of the revised words is subtracted from the higher revision and added to the lower one.
                    $this->decreaseCountersAndRemoveIssues( $chunkReview );
                    $unsetFinalRevision[] = $chunkReview->source_page;
                    $chunkReviews[]       = $chunkReview;

                    // in this case we are handling the chunk ( state ) where the segment must go
                } elseif ( $this->_event->currentEventIsOnThisChunk( $chunkReview ) ) {

                    // There is a downgrade on this review, and it is the first time it happens
                    // we must add the reviewed word count
                    if ( !$this->aFinalRevisionExistsForThisChunk( $chunkReview ) ) {
                        $chunkReview->reviewed_words_count += $this->_event->getSegmentStruct()->raw_word_count;
                    }

                    // in this case the tte is added by definition
                    $chunkReview->total_tte      += $this->_event->getCurrentEvent()->time_to_edit;
                    $chunkReviews[]              = $chunkReview;

                } elseif ( $this->aFinalRevisionExistsForThisChunk( $chunkReview ) ) {

                    // this case fits any other intermediate chunkReview record when an event exists
                    // i.e.: R3 -> R1 with an event existing in R2
                    $this->decreaseCountersAndRemoveIssues( $chunkReview );
                    $unsetFinalRevision[] = $chunkReview->source_page;
                    $chunkReviews[]       = $chunkReview;

                }

            } elseif (
                    $this->_event->isDowngradedToTranslated() &&
                    $this->_event->getCurrentEventSourcePage() == Constants::SOURCE_PAGE_TRANSLATE // this is redundant, but we add it for security
            ) {

                // we are coming from R1 or R2 to Translated
                if ( $this->aFinalRevisionExistsForThisChunk( $chunkReview ) ) {

                    // this segment already has been in this revision state
                    // reviewed words are discounted from R1/R2
                    $this->decreaseCountersAndRemoveIssues( $chunkReview );
                    $unsetFinalRevision[] = $chunkReview->source_page;

                }

                $chunkReviews[] = $chunkReview;

            } elseif (
                    $this->_event->isEditingCurrentRevision() &&
                    $this->_event->currentEventIsOnThisChunk( $chunkReview )
            ) {

                // handle further modifications on the same phase R1/R2, we need to increase TTE only
                // to count how much time the translator has been on this segment
                $chunkReview->total_tte += $this->_event->getCurrentEvent()->time_to_edit;
                $chunkReviews[]         = $chunkReview;

            } elseif (

                    /*
                     * THIS IS THE HARDEST PART TO UNDERSTAND
                     *
                     * Here we treat ICEs and pre-translated segments specifically, they must be handled here only for the FIRST STATUS CHANGE
                     *
                     * When the ICE has NOT BEEN modified before.
                     *
                     *       From ICE (approved) to Approved R2: changes up are made to the progress bar
                     *              and the raw words in the segment are added to the reviewed words for R2.
                     *       From ICE (approved) to Approved R1:
                     *          If the reviewer has edited the target text, changes up are made to the progress bar
                     *              and the raw words in the segment are added to the reviewed words for R1.
                     *          If the reviewer has not edited the target text, no changes.
                     *              BUT this is implicit because we never reached this function
                     */
                    $this->_event->iceIsAboutToBeReviewedForTheFirstTime() &&
                    ( $this->_event->isR2() || $this->_event->isModifiedIce() )
                    && $this->_event->currentEventIsOnThisChunk( $chunkReview ) // only one chunk match, R1, R2, R(N) they are mutually excluded

            ) {
                $this->increaseAllCounters( $chunkReview );
                $chunkReviews[] = $chunkReview;
            } else { // Upper transition

                /*
                 * HERE we are handling the UPWARD Revision
                 *
                 * *** NOTE ***
                 *
                 * when an ICE is modified/handled for the first time, those conditions are always true,
                 * and they are not distinguishable
                 *
                 * BUT it's supposed that the first modification entered the previous conditional branch
                 * AND that an UNMODIFIED ICE never reach this code BUT pre-translated will do
                 */
                if ( $this->_event->lastEventWasOnThisChunk( $chunkReview ) ) {

                    // this is a transition from R1 to R2, otherwise is a direct transition from ICE to R2
                    // this is needed to understand if an event already been made in this specific phase ( we are handling ALL the chunk reviews )
                    // to allow the discount of words
                    if ( $this->aFinalRevisionExistsForThisChunk( $chunkReview ) ) {
                        $chunkReviews[] = $chunkReview;
                    }

                } elseif ( $this->_event->currentEventIsOnThisChunk( $chunkReview ) ) { // when an ICE is modified/handled for the first time this is always true

                    $this->increaseAllCounters( $chunkReview );
                    $chunkReviews[] = $chunkReview;

                }

            }

        }

        foreach ( $chunkReviews as $chunkReview ) {
            $segmentReviewTransitionModel->addChunkReview( $chunkReview );
        }

        foreach ( $this->_issuesDeletionList as $issuesToDelete ) {
            foreach ( $issuesToDelete as $issueToDelete ) {
                $segmentReviewTransitionModel->addIssueToDelete( $issueToDelete );
            }
        }

        $segmentReviewTransitionModel->unsetFinalRevision( $unsetFinalRevision );

        return $segmentReviewTransitionModel;
    }

    /**
     * @throws Exception
     */
    public function sendNotificationEmail() {
        if ( $this->_event->isPropagationSource() && $this->_event->isBeingLowerReviewedOrTranslated() ) {
            $chunkReviewsWithFinalRevisions = [];
            foreach ( $this->_chunkReviews as $chunkReview ) {
                if ( in_array( $chunkReview->source_page, $this->_sourcePagesWithFinalRevisions ) ) {
                    $chunkReviewsWithFinalRevisions[ $chunkReview->source_page ] = $chunkReview;
                }
            }

            $this->_sendNotificationEmail( $this->_finalRevisions, $chunkReviewsWithFinalRevisions );
        }
    }

    /**
     * @param $source_page
     */
    protected function flagIssuesToBeDeleted( $source_page ) {
        $issue = EntryDao::findByIdSegmentAndSourcePage( $this->_event->getSegmentStruct()->id, $this->_chunk->id, $source_page );

        if ( $issue ) {
            $this->_issuesDeletionList[ $source_page ] = $issue;
        }
    }

    /**
     * @param $finalRevisions
     * @param $chunkReviewsWithFinalRevisions
     *
     * @throws Exception
     */
    protected function _sendNotificationEmail( $finalRevisions, $chunkReviewsWithFinalRevisions ) {
        $emails                   = [];
        $userWhoChangedTheSegment = $this->_event->getEventUser();
        $revision                 = $chunkReviewsWithFinalRevisions[ $this->_event->getPreviousEventSourcePage() ];

        $serialized_issues = [];
        if ( isset( $this->_issuesDeletionList[ $this->_event->getPreviousEventSourcePage() ] ) ) {

            /** @var EntryWithCategoryStruct $issue */
            foreach ( $this->_issuesDeletionList[ $this->_event->getPreviousEventSourcePage() ] as $k => $issue ) {
                $serialized               = $issue->toArray();
                $serialized[ 'comments' ] = [];

                /** @var EntryCommentStruct $comment */
                foreach ( $issue->getComments() as $comment ) {
                    $serialized[ 'comments' ][] = $comment->toArray();
                }

                $serialized_issues [] = $serialized;
            }

        }

        $segmentInfo = [
                'segment_source'  => \Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_event->getSegmentStruct()->segment ),
                'old_translation' => \Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_event->getOldTranslation()->translation ),
                'new_translation' => \Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_event->getWantedTranslation()->translation ),
                'issues'          => $serialized_issues
        ];

        foreach ( $finalRevisions as $finalRevision ) {
            if ( $finalRevision->source_page != $this->_event->getPreviousEventSourcePage() ) {
                continue;
            }

            $user = ( new Users_UserDao() )->getByUid( $finalRevision->uid );
            if ( $user ) {
                $emails[] = [
                        'isPreviousChangeAuthor' => true,
                        'recipient'              => $user,
                ];
            }
        }

        $projectOwner = ( new Users_UserDao() )->getByEmail( $this->_chunk->getProject()->id_customer );
        if ( $projectOwner ) {
            $emails[] = [
                    'isPreviousChangeAuthor' => false,
                    'recipient'              => $projectOwner,
            ];
        }

        $projectAssignee = ( new Users_UserDao() )->getByUid( $this->_chunk->getProject()->id_assignee );
        if ( $projectAssignee ) {
            $emails[] = [
                    'isPreviousChangeAuthor' => false,
                    'recipient'              => $projectAssignee,
            ];
        }

        $emails = $this->_chunk->getProject()->getFeaturesSet()->filter( 'filterRevisionChangeNotificationList', $emails );
        $url    = Routes::revise( $this->_chunk->getProject()->name, $revision->id_job, $revision->review_password,
                $this->_chunk->source, $this->_chunk->target, [
                        'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $revision->source_page ),
                        'id_segment'      => $this->_event->getSegmentStruct()->id
                ] );


        $notifiedEmails = [];
        foreach ( $emails as $email ) {
            $recipientEmail = $email[ 'recipient' ]->email;

            if ( !in_array( $recipientEmail, $notifiedEmails ) ) {
                $delivery = new RevisionChangedNotificationEmail( $segmentInfo, $email, $url, $userWhoChangedTheSegment );
                $delivery->send();
                $notifiedEmails[] = $recipientEmail;
            }
        }
    }

    /**
     * Returns the sum of penalty points to subtract, reading from the previously populated _issuesDeletionList.
     *
     * @param $source_page
     *
     * @return int
     */
    protected function getPenaltyPointsForSourcePage( $source_page ) {
        if ( !isset( $this->_issuesDeletionList[ $source_page ] ) || !is_array( $this->_issuesDeletionList[ $source_page ] ) ) {
            return 0;
        }

        return array_reduce( $this->_issuesDeletionList[ $source_page ], function ( $carry, EntryStruct $issue ) {
            return $carry + $issue->penalty_points;
        }, 0 );
    }

}