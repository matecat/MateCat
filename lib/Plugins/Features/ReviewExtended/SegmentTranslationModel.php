<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/24/16
 * Time: 10:21 AM
 */

namespace Features\ReviewExtended;

use ChunkReviewTransition\ChunkReviewTransitionModel;
use ChunkReviewTransition_ChunkReviewTransitionModel;
use ChunkReviewTransition_UnitOfWork;
use Chunks_ChunkStruct;
use Constants;
use Features\ISegmentTranslationModel;
use Features\SecondPassReview\Email\RevisionChangedNotificationEmail;
use Features\SecondPassReview\Model\SegmentTranslationEventDao;
use Features\TranslationVersions\Model\SegmentTranslationEventStruct;
use LQA\ChunkReviewStruct;
use LQA\EntryCommentStruct;
use LQA\EntryDao;
use LQA\EntryStruct;
use LQA\EntryWithCategoryStruct;
use Routes;
use SegmentTranslationChangeVector;
use TransactionableTrait;
use Users_UserDao;
use Users_UserStruct;

class SegmentTranslationModel implements ISegmentTranslationModel {

    use TransactionableTrait;

    /**
     * @var SegmentTranslationChangeVector
     */
    protected $_model;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $_chunk;

    protected $affectedChunkReviews = [];

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

    public function __construct( SegmentTranslationChangeVector $model, array $chunkReviews ) {
        $this->_model        = $model;
        $this->_chunkReviews = $chunkReviews;
        $this->_chunk        = $model->getChunk();
        $this->_project      = $this->_chunk->getProject();

        $this->_finalRevisions = ( new SegmentTranslationEventDao() )->getFinalRevisionsForSegment(
                $this->_chunk->id, $this->_model->getSegmentStruct()->id
        );

        $this->_sourcePagesWithFinalRevisions = array_map( function ( SegmentTranslationEventStruct $event ) {
            return $event->source_page;
        }, $this->_finalRevisions );
    }

    /**
     * @return ChunkReviewTransitionModel
     * @throws \Exception
     */
    public function getChunkReviewTransitionModel() {

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
         * 3. we are moving down exiting a reviewed state
         * 4. we are moving down from a reviewed state to another
         *
         * 5. we are not changing the reviewed state.
         * 6. we are not changing the translated state.
         *
         */

        $chunkReviews          = [];
        $unsetFinalRevision    = [];
        $originSourcePage      = $this->_model->getEventModel()->getOriginSourcePage();
        $destinationSourcePage = $this->_model->getEventModel()->getDestinationSourcePage();

        $reviewTransitionModel = new ChunkReviewTransitionModel( $this->_model );

        // populate structs for current segment and propagations
        for ( $i = 0; $i < count( $this->_chunkReviews ); $i++ ) {

            // build a new ChunkReviewStruct
            $chunkReview              = new ChunkReviewStruct();
            $chunkReview->id          = $this->_chunkReviews[ $i ]->id;
            $chunkReview->id_project  = $this->_chunkReviews[ $i ]->id_project;
            $chunkReview->id_job      = $this->_chunkReviews[ $i ]->id_job;
            $chunkReview->password    = $this->_chunkReviews[ $i ]->password;
            $chunkReview->source_page = $this->_chunkReviews[ $i ]->source_page;

            if ( $this->_model->isEnteringReviewedState() && $destinationSourcePage == $chunkReview->source_page ) {
                // expect the first chunk review record to be the final
                // add revised words and advancement
                $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count;
                $chunkReview->total_tte            += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit;

                if ( $destinationSourcePage != $originSourcePage ) {
                    $chunkReview->advancement_wc += $this->advancementWordCount();
                }

                $chunkReviews[] = $chunkReview;

                break;
            } elseif ( $this->_model->isExitingReviewedState() ) {
                // expect the direction to be downwards from R3 -> R2 -> R1 etc.
                if ( in_array( $chunkReview->source_page, $this->_sourcePagesWithFinalRevisions ) ) {
                    $chunkReview->reviewed_words_count -= $this->_model->getSegmentStruct()->raw_word_count;
                    $this->_addIssuesToDelete( $chunkReview->source_page );
                    $chunkReview->penalty_points -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page );
                    $unsetFinalRevision []       = $chunkReview->source_page;
                    $chunkReviews[]              = $chunkReview;
                }

                if ( $chunkReview->source_page == $originSourcePage ) {
                    $chunkReview->advancement_wc -= $this->advancementWordCount();
                    $chunkReviews[]              = $chunkReview;
                }
            } // TODO: in the following two cases we should consider if the segment is changed or not.
            elseif ( $this->_model->isBeingLowerReviewed() ) {
                // whenever a revision is lower reviewed we expect the upper revisions to be invalidated.
                // the reviewed words count is removed from the upper one and moved to the lower one.
                if ( $originSourcePage == $chunkReview->source_page ) {
                    $chunkReview->reviewed_words_count -= $this->_model->getSegmentStruct()->raw_word_count;
                    $this->_addIssuesToDelete( $chunkReview->source_page );
                    $chunkReview->penalty_points -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page );
                    $chunkReview->advancement_wc -= $this->advancementWordCount();
                    $unsetFinalRevision[]        = $chunkReview->source_page;
                    $chunkReviews[]              = $chunkReview;


                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    // TODO: evaluate the case in which the destination revision never received a revision before
                    // evaluate $sourcePagesWithFinalRevisions
                    if ( !in_array( $chunkReview->source_page, $this->_sourcePagesWithFinalRevisions ) ) {
                        $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count;
                    }

                    $chunkReview->advancement_wc += $this->advancementWordCount();
                    $chunkReview->total_tte      += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit;
                    $chunkReviews[]              = $chunkReview;

                } elseif ( in_array( $chunkReview->source_page, $this->_sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    $chunkReview->reviewed_words_count -= $this->_model->getSegmentStruct()->raw_word_count;
                    $this->_addIssuesToDelete( $chunkReview->source_page );
                    $chunkReview->penalty_points -= $this->getPenaltyPointsForSourcePage( $chunkReview->source_page );
                    $unsetFinalRevision[]        = $chunkReview->source_page;
                    $chunkReviews[]              = $chunkReview;
                }

            } elseif ( $this->_model->isBeingUpperReviewed() ) {
                if ( $originSourcePage == $chunkReview->source_page ) {
                    // TODO: decide wether or not to remove the revised words
                    // expect advancement to be assigned to the origin source_page
                    $chunkReview->advancement_wc -= $this->advancementWordCount();
                    $chunkReviews[]              = $chunkReview;


                } elseif ( $destinationSourcePage == $chunkReview->source_page ) {
                    // we reached the last record, destination record of the lower revision, add the count
                    $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count;
                    $chunkReview->advancement_wc       += $this->advancementWordCount();
                    $chunkReview->total_tte            += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit;
                    $chunkReviews[]                    = $chunkReview;

                } elseif ( in_array( $chunkReview->source_page, $this->_sourcePagesWithFinalRevisions ) ) {
                    // this case fits any other intermediate chunkReview record
                    // in case of upper revisions this case should never happen because latest state is always
                    // the current revision state so it's not possible to move from R1 to R3 if an R2 is current
                    // state.
                    false;
                }
            } elseif (
                    $this->_model->isModifyingICEFromTranslation() &&
                    $this->_model->getEventModel()->getDestinationSourcePage() == Constants::SOURCE_PAGE_TRANSLATE
            ) {
                /**
                 * Enter this condition when we are just changing source page. This change only affects advancement wc.
                 * This is the case of ICE matches moving from R1 to TR.
                 */
                $chunkReview->advancement_wc -= $this->advancementWordCount();
                $chunkReviews[]              = $chunkReview;

            } elseif ( $this->_model->isEditingCurrentRevision() && $destinationSourcePage == $chunkReview->source_page ) {
                $chunkReview->total_tte += $this->_model->getEventModel()->getCurrentEvent()->time_to_edit;

                if ( $this->_model->isModifyingICEFromRevisionOne() ) {
                    $chunkReview->reviewed_words_count += $this->_model->getSegmentStruct()->raw_word_count;
                }

                $chunkReviews[] = $chunkReview;
            }
        }

        foreach ( $chunkReviews as $chunkReview ) {
            $reviewTransitionModel->addChunkReview( $chunkReview );
        }

        foreach ( $this->_issuesDeletionList as $issuesToDelete ) {
            foreach ( $issuesToDelete as $issueToDelete ) {
                $reviewTransitionModel->addIssueToDelete( $issueToDelete );
            }
        }

        $reviewTransitionModel->setUnsetFinalRevision( $unsetFinalRevision );

        return $reviewTransitionModel;
    }

    /**
     * @throws \Exception
     */
    public function sendNotificationEmail() {
        if ( $this->_model->getEventModel()->isPropagationSource() && $this->_model->isBeingLowerReviewedOrTranslated() ) {
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
    protected function _addIssuesToDelete( $source_page ) {
        $issue = EntryDao::findByIdSegmentAndSourcePage( $this->_model->getSegmentStruct()->id, $this->_chunk->id, $source_page );

        if ( $issue ) {
            $this->_issuesDeletionList[ $source_page ] = $issue;
        }
    }

    /**
     * @param $finalRevisions
     * @param $chunkReviewsWithFinalRevisions
     *
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \ReflectionException
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    protected function _sendNotificationEmail( $finalRevisions, $chunkReviewsWithFinalRevisions ) {
        $emails                   = [];
        $userWhoChangedTheSegment = $this->_model->getEventUser();
        $revision                 = $chunkReviewsWithFinalRevisions[ $this->_model->getEventModel()->getOriginSourcePage() ];

        $serialized_issues = [];
        if( isset( $this->_issuesDeletionList[ $this->_model->getEventModel()->getOriginSourcePage() ] ) ){

            /** @var EntryWithCategoryStruct $issue */
            foreach ( $this->_issuesDeletionList[ $this->_model->getEventModel()->getOriginSourcePage() ] as $k => $issue ) {
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
                'segment_source'  => \Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_model->getSegmentStruct()->segment ),
                'old_translation' => \Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_model->getEventModel()->getOldTranslation()->translation ),
                'new_translation' => \Utils::htmlentitiesToUft8WithoutDoubleEncoding( $this->_model->getEventModel()->getTranslation()->translation ),
                'issues'          => $serialized_issues
        ];

        foreach ( $finalRevisions as $finalRevision ) {
            if ( $finalRevision->source_page != $this->_model->getEventModel()->getOriginSourcePage() ) {
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
                        'id_segment'      => $this->_model->getSegmentStruct()->id
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

    protected function advancementWordCount() {
        if ( $this->_model->getEventModel()->getOldTranslation()->isICE() || $this->_model->getEventModel()->getOldTranslation()->isPreTranslated() ) {
            return $this->_model->getSegmentStruct()->raw_word_count;
        }

        return $this->_model->getOldTranslation()->eq_word_count;
    }

    /**
     * Returns the sum of penalty points to subtract, reading from the previously populated _issuesDeletionList.
     *
     * @param $source_page
     *
     * @return mixed
     */
    protected function getPenaltyPointsForSourcePage( $source_page ) {
        return array_reduce( $this->_issuesDeletionList[ $source_page ], function ( $carry, EntryStruct $issue ) {
            return $carry + $issue->penalty_points;
        }, 0 );
    }

    /**
     * Unset the final_revision flag from any revision we removed reviwed_words.
     * Apply final_revision flag to the current event.
     *
     * If the current event is a revision, ensure the source_page is included in the
     * unset list.
     *
     * @param $unsetFinalRevision
     *
     * @throws \Exception
     */
    protected function updateFinalRevisionFlag( $unsetFinalRevision ) {
        $eventStruct = $this->_model->getEventModel()->getCurrentEvent();
        $is_revision = (int)$eventStruct->source_page > Constants::SOURCE_PAGE_TRANSLATE;

        if ( $is_revision ) {
            $unsetFinalRevision = array_merge( $unsetFinalRevision, [ $eventStruct->source_page ] );
        }

        if ( !empty( $unsetFinalRevision ) ) {
            ( new SegmentTranslationEventDao() )->unsetFinalRevisionFlag(
                    $this->_chunk->id, [ $this->_model->getSegmentStruct()->id ], $unsetFinalRevision
            );
        }

        $eventStruct->final_revision = $is_revision;
        SegmentTranslationEventDao::updateStruct( $eventStruct, [ 'fields' => [ 'final_revision' ] ] );
    }

}