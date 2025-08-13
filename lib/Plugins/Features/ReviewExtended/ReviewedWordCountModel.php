<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Plugins\Features\ReviewExtended;

use Exception;
use Model\DataAccess\TransactionalTrait;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryCommentStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserDao;
use Model\WordCount\CounterModel;
use Plugins\Features\ReviewExtended\Email\RevisionChangedNotificationEmail;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\Model\TranslationEventStruct;
use ReflectionException;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;

class ReviewedWordCountModel implements IReviewedWordCountModel {

    use TransactionalTrait;

    /**
     * @var TranslationEvent
     */
    protected TranslationEvent $_event;

    /**
     * @var ?JobStruct
     */
    protected ?JobStruct $_chunk;

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $_project;

    /**
     * @var ChunkReviewStruct[]
     */
    protected array $_chunkReviews;

    /**
     * @var array
     */
    protected array $_sourcePagesWithFinalRevisions;

    /**
     * @var array
     */
    private array $_finalRevisions;
    /**
     * @var CounterModel
     */
    private CounterModel $_jobWordCounter;

    /**
     * @throws ReflectionException
     */
    public function __construct( TranslationEvent $event, CounterModel $jobWordCounter, array $chunkReviews ) {
        $this->_event          = $event;
        $this->_chunkReviews   = $chunkReviews;
        $this->_chunk          = $event->getChunk();
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
     * @return TranslationEvent
     */
    public function getEvent(): TranslationEvent {
        return $this->_event;
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
    private function decreaseCounters( ChunkReviewStruct $chunkReview ) {
        // when downgrading a revision to translation, the issues must be removed (from R1, R2 or both)
        $this->flagIssuesToBeDeleted( $chunkReview->source_page );
        $chunkReview->reviewed_words_count -= $this->_event->getSegmentStruct()->raw_word_count;
        $chunkReview->penalty_points       -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page );

        $this->_event->setFinalRevisionToRemove( $chunkReview->source_page );
        $this->_event->setChunkReviewForPassFailUpdate( $chunkReview );
    }

    /**
     * @throws Exception
     */
    private function increaseCountersButCheckForFinalRevision( ChunkReviewStruct $chunkReview ) {
        // There is a change status to this review, we must check if is the first time it happens;
        // in that case, we must add the reviewed word count
        if ( !$this->aFinalRevisionExistsForThisChunk( $chunkReview ) ) {
            $chunkReview->reviewed_words_count += $this->_event->getSegmentStruct()->raw_word_count;
        } else {
            $this->_event->setFinalRevisionToRemove( $chunkReview->source_page ); // remove the previous final flag to allow the new one
        }

        // in this case, the tte is added by definition
        $chunkReview->total_tte += $this->_event->getTranslationEventStruct()->time_to_edit;

        $this->_event->setChunkReviewForPassFailUpdate( $chunkReview );
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
     * @throws Exception
     */
    public function evaluateChunkReviewEventTransitions(): void {

        if ( $this->_event->isChangingStatus() ) {
            $this->_jobWordCounter->setOldStatus( $this->_event->getOldTranslation()->status );
            $this->_jobWordCounter->setNewStatus( $this->_event->getWantedTranslation()->status );
            $this->_jobWordCounter->setUpdatedValues( $this->_event->getOldTranslation()->eq_word_count, $this->_event->getSegmentStruct()->raw_word_count );
        }

        // populate structs for current segment and propagations
        // we are iterating on ALL the revision levels (chunks)
        for ( $i = 0; $i < count( $this->_chunkReviews ); $i++ ) {

            // build a new ChunkReviewStruct for partials
            $chunkReview              = new ChunkReviewStruct();
            $chunkReview->id          = $this->_chunkReviews[ $i ]->id;
            $chunkReview->id_project  = $this->_chunkReviews[ $i ]->id_project;
            $chunkReview->id_job      = $this->_chunkReviews[ $i ]->id_job;
            $chunkReview->password    = $this->_chunkReviews[ $i ]->password;
            $chunkReview->source_page = $this->_chunkReviews[ $i ]->source_page;

            if ( $this->_event->isADraftChange() ) {
                continue;
            } elseif ( $this->_event->isChangingStatus() ) {

                if ( $this->_event->currentEventIsOnThisChunk( $chunkReview ) ) {

                    // There is a change status to this review, we must check if is the first time it happens;
                    // in that case, we must add the reviewed word count
                    // otherwise remove the previous final flag to allow the new one
                    $this->increaseCountersButCheckForFinalRevision( $chunkReview );

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
                    $this->decreaseCounters( $chunkReview );

                }

            } elseif ( $this->_event->isIce() ) {

                if (
                        // This case happens because we have the same status for ICEs and Approved segments.
                    // All can pass except unmodified ices
                    // Rule 3:
                    //   3. For unmodified ICE segments, the progress is not counted unless there is a change of status (acceptance doesn't count)
                        !$this->_event->isUnModifiedIce() &&
                        $this->_event->currentEventIsOnThisChunk( $chunkReview )
                ) {

                    // There is an ICE segment acceptance with or without modifications in the same revision phase.
                    // - If it is the first time it's happened, we must add the reviewed word count.
                    // - If it is not the first modification, we will find a revision flag, will not increase the reviewed word count but will unset the previous final flag
                    $this->increaseCountersButCheckForFinalRevision( $chunkReview );

                } elseif ( $this->_event->currentEventIsOnThisChunk( $chunkReview ) ) {
                    /*
                     * R1/R2 Accept (without modifications) an ICE revision on the same level; we want not to flag them as final revision (only track the acceptance)
                     */
                    $this->_event->setRevisionFlagAllowed( false );
                }

            } else {

                /*
                 * No change status
                 * Here:
                 * - 1) R1/R2 Modify a revision made by him on the same level
                 * - 2) Translation events
                */
                if ( $this->_event->currentEventIsOnThisChunk( $chunkReview ) ) {
                    /*
                     * - 1) R1/R2 Modify an existent revision made by him on the same level
                     */
                    $this->increaseCountersButCheckForFinalRevision( $chunkReview );

                } elseif ( $this->_event->getWantedTranslation()->isTranslationStatus() ) {
                    /*
                     * - 2) Handle translation events since they are not revisions
                     */
                    $this->_event->setRevisionFlagAllowed( false );
                }

            }

        }

    }

    /**
     * Delete all issues
     *
     */
    public function deleteIssues() {
        foreach ( $this->_event->getIssuesToDelete() as $issue ) {
            $issue->addComments( ( new EntryCommentStruct() )->getEntriesById( $issue->id ) );
            EntryDao::deleteEntry( $issue );
        }
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
     * @param int $source_page
     *
     * @throws ReflectionException
     */
    private function flagIssuesToBeDeleted( int $source_page ) {

        $issue = EntryDao::findByIdSegmentAndSourcePage( $this->_event->getSegmentStruct()->id, $this->_chunk->id, $source_page );
        foreach ( $issue as $issueToDelete ) {
            $this->_event->addIssueToDelete( $issueToDelete );
        }

    }

    /**
     * @param $finalRevisions
     * @param $chunkReviewsWithFinalRevisions
     *
     * @throws Exception
     */
    private function _sendNotificationEmail( $finalRevisions, $chunkReviewsWithFinalRevisions ) {
        $emails                   = [];
        $userWhoChangedTheSegment = $this->_event->getUser();
        $revision                 = $chunkReviewsWithFinalRevisions[ $this->_event->getPreviousEventSourcePage() ] ?? null;

        $serialized_issues = [];

        foreach ( $this->_event->getIssuesToDelete() as $issue ) {
            $serialized               = $issue->toArray();
            $serialized[ 'comments' ] = [];

            /** @var EntryCommentStruct $comment */
            foreach ( $issue->getComments() as $comment ) {
                $serialized[ 'comments' ][] = $comment->toArray();
            }

            $serialized_issues [] = $serialized;
        }

        $segmentInfo = [
            'segment_source'  => Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_event->getSegmentStruct()->segment ),
            'old_translation' => Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_event->getOldTranslation()->translation ),
            'new_translation' => Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_event->getWantedTranslation()->translation ),
            'old_status'      => $this->_event->getOldTranslation()->status,
            'new_status'      => $this->_event->getWantedTranslation()->status,
            'issues'          => $serialized_issues
        ];

        foreach ( $finalRevisions as $finalRevision ) {
            if ( $finalRevision->source_page != $this->_event->getPreviousEventSourcePage() ) {
                continue;
            }

            $user = ( new UserDao() )->getByUid( $finalRevision->uid );
            if ( $user ) {
                $emails[] = [
                        'isPreviousChangeAuthor' => true,
                        'recipient'              => $user,
                ];
            }
        }

        $projectOwner = ( new UserDao() )->getByEmail( $this->_chunk->getProject()->id_customer );
        if ( $projectOwner ) {
            $emails[] = [
                    'isPreviousChangeAuthor' => false,
                    'recipient'              => $projectOwner,
            ];
        }

        $projectAssignee = ( new UserDao() )->getByUid( $this->_chunk->getProject()->id_assignee );
        if ( $projectAssignee ) {
            $emails[] = [
                    'isPreviousChangeAuthor' => false,
                    'recipient'              => $projectAssignee,
            ];
        }

        $emails = $this->_chunk->getProject()->getFeaturesSet()->filter( 'filterRevisionChangeNotificationList', $emails );

        if( !empty( $revision ) ){
            $url = CanonicalRoutes::revise(
                    $this->_chunk->getProject()->name,
                    $revision->id_job,
                    $revision->review_password,
                    $this->_chunk->source,
                    $this->_chunk->target,
                    [
                            'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $revision->source_page ),
                            'id_segment'      => $this->_event->getSegmentStruct()->id
                    ]
            );
        } else {
            // handle the case when an ICE OR a pre-translated segment (no previous events) changes its status to a lower status
            // use the event chunk to generate the link.
            $url = CanonicalRoutes::translate(
                    $this->_chunk->getProject()->name,
                    $this->_chunk->id,
                    $this->_chunk->password,
                    $this->_chunk->source,
                    $this->_chunk->target
            );
        }


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
     * @param int $source_page
     *
     * @return int
     */
    private function getPenaltyPointsForSourcePage( int $source_page ): int {

        $toReduce = $this->_event->getIssuesToDelete();
        $issues   = array_filter( $toReduce, function ( EntryStruct $issue ) use ( $source_page ) {
            return $issue->source_page == $source_page;
        } );

        return array_reduce( $issues, function ( $carry, EntryStruct $issue ) {
            return $carry + $issue->penalty_points;
        }, 0 );

    }

}