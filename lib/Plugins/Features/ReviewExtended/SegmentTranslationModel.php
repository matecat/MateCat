<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use Chunks_ChunkStruct;
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
use Projects_ProjectStruct;
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
     * @var Projects_ProjectStruct
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

        $this->_finalRevisions = ( new TranslationEventDao() )->getAllFinalRevisionsForSegment(
                $this->_chunk->id,
                $this->_event->getSegmentStruct()->id
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
    private function aFinalRevisionExistsForThisChunk( ChunkReviewStruct $chunkReview ): bool {
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

        // when downgrading a revision to translation, the issues must be removed (from R1, R2 or both)
        $this->flagIssuesToBeDeleted( $chunkReview->source_page );
    }

    private function increaseCountersButCheckForFinalRevision( ChunkReviewStruct $chunkReview ) {
        // There is a change status or acceptance to this review, and it is the first time it happens;
        // we must add the reviewed word count
        if ( !$this->aFinalRevisionExistsForThisChunk( $chunkReview ) ) {
            $chunkReview->reviewed_words_count += $this->_event->getSegmentStruct()->raw_word_count;
        }

        // in this case, the tte is added by definition
        $chunkReview->total_tte += $this->_event->getCurrentEvent()->time_to_edit;
    }

    /**
     * Rules
     *
     * All progress counts of reviewed words in R1 and R2 no longer take into account the concept of pre-translation and are updated as follows:
     *
     * 1. Based on the change of status
     * 2. Upon pressing the "APPROVE" button, when modifying a segment in the same status or accepting the segment without changes
     *    - After the first modification or acceptance, the count does not increase further unless there is a change of status
     * 3. For unmodified ICE segments, the progress is not counted unless there is a change of status (no acceptance counts)
     *
     * @return ChunkReviewTranslationEventTransition
     * @throws Exception
     */
    public function evaluateAndGetChunkReviewTranslationEventTransition(): ChunkReviewTranslationEventTransition {

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
        // we are iterating on ALL the revision levels (chunks)
        for ( $i = 0; $i < count( $this->_chunkReviews ); $i++ ) {

            // build a new ChunkReviewStruct
            $chunkReview              = new ChunkReviewStruct();
            $chunkReview->id          = $this->_chunkReviews[ $i ]->id;
            $chunkReview->id_project  = $this->_chunkReviews[ $i ]->id_project;
            $chunkReview->id_job      = $this->_chunkReviews[ $i ]->id_job;
            $chunkReview->password    = $this->_chunkReviews[ $i ]->password;
            $chunkReview->source_page = $this->_chunkReviews[ $i ]->source_page;

            if ( $this->_event->isADraftChange() ) {
                continue;
            }

            if ( $this->_event->isChangingStatus() ) {

                if ( $this->_event->currentEventIsOnThisChunk( $chunkReview ) ) {

                    // There is a change status to this review, and it is the first time it happens;
                    // we must add the reviewed word count
                    $this->increaseCountersButCheckForFinalRevision( $chunkReview );
                    $chunkReviews[] = $chunkReview;

                } elseif ( $this->aFinalRevisionExistsForThisChunk( $chunkReview ) && $this->_event->isLowerTransition() ) {  // check for lower transition, we want to not decrement when upgrading statuses

                    // This case fits any chunkReview record when an event exists on it.
                    // Whenever a revision is lower reviewed, we expect the upper revisions to be invalidated.
                    // The value of the revised words is subtracted from the higher revision and added to the lower one (in the previous conditional branch).
                    //
                    // Moreover,
                    // When a segment goes from R2 to T with an event existing in R1,
                    // R2 and R1 will pass inside this branch, and they will be decreased.
                    //
                    // This segment already has been in R1 revision state
                    // reviewed words are discounted from R1
                    $this->decreaseCountersAndRemoveIssues( $chunkReview );
                    $unsetFinalRevision[] = $chunkReview->source_page;
                    $chunkReviews[]       = $chunkReview;

                }

            } elseif (
                    // All can pass except unmodified ices
                    !$this->_event->isUnModifiedIce() &&
                    $this->_event->currentEventIsOnThisChunk( $chunkReview )
            ) {

                // There is a segment acceptance with or without modifications in the same revision phase, and it is the first time it's happened;
                // we must add the reviewed word count
                $this->increaseCountersButCheckForFinalRevision( $chunkReview );
                $chunkReviews[] = $chunkReview;

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
        if ( $this->_event->isPropagationSource() && $this->_event->isLowerTransition() ) {
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
    protected function getPenaltyPointsForSourcePage( $source_page ): int {
        if ( !isset( $this->_issuesDeletionList[ $source_page ] ) || !is_array( $this->_issuesDeletionList[ $source_page ] ) ) {
            return 0;
        }

        return array_reduce( $this->_issuesDeletionList[ $source_page ], function ( $carry, EntryStruct $issue ) {
            return $carry + $issue->penalty_points;
        }, 0 );
    }

}