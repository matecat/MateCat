<?php

namespace Features\TranslationVersions\Model;

use Chunks_ChunkStruct;
use Constants;
use Constants_TranslationStatus;
use Database;
use Exception;
use Exceptions\ValidationError;
use Segments_SegmentDao;
use Segments_SegmentStruct;
use Translations_SegmentTranslationStruct;
use Users_UserDao;
use Users_UserStruct;

class TranslationEvent {

    /**
     * @var Translations_SegmentTranslationStruct
     */
    protected $old_translation;

    /**
     * @var Translations_SegmentTranslationStruct
     */
    protected $wanted_translation;

    protected $user;

    protected $source_page;

    /**
     * @var TranslationEventStruct
     */
    protected $previous_event;

    /**
     * @var TranslationEventStruct
     */
    protected $current_event;

    protected $_isPropagationSource = true;

    /**
     * @var Chunks_ChunkStruct|null
     */
    private $chunk;

    public function __construct( Translations_SegmentTranslationStruct $old_translation,
                                 Translations_SegmentTranslationStruct $translation,
                                                                       $user, $source_page_code ) {

        $this->old_translation    = $old_translation;
        $this->wanted_translation = $translation;
        $this->user               = $user;
        $this->source_page        = $source_page_code;
        $this->chunk              = $this->wanted_translation->getChunk();

        $this->getPreviousEvent();
    }


    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getWantedTranslation() {
        return $this->wanted_translation;
    }

    /**
     * @return Users_UserStruct|null
     * @throws Exception
     */
    public function getEventUser() {
        if ( $this->getCurrentEvent()->uid ) {
            return ( new Users_UserDao() )->getByUid( $this->getCurrentEvent()->uid );
        }
    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws Exception
     */
    public function getOldTranslation() {
        if ( is_null( $this->old_translation ) ) {
            throw new Exception( 'Old translation is not set' );
        }

        return $this->old_translation;
    }

    /**
     * Origin source page can be missing. This can happen in case of ICE matches, or pre-transalted
     * segments or just because we are evaluating a transition from NEW to TRANSLATED status.
     *
     * In such case we need to make assumptions on the `source_page` variable because that's what we use
     * to decide where to move revised words and advancement words around.
     * @return mixed
     * @throws Exception
     */
    public function isBeingUpperReviewed() {
        return $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isReviewedStatus() &&
                $this->isUpperRevision();
    }

    public function isBeingLowerReviewed() {
        return $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isReviewedStatus() &&
                $this->isLowerRevision();
    }

    public function isBeingLowerReviewedOrTranslated() {
        return $this->isLowerRevision();
    }

    /**
     * Returns 1 if source page is moving up  0 if it's not changing, -1 if it's moving down.
     *
     * @return int
     */
    public function getSourcePageDirection() {
        $originSourcePage      = $this->getPreviousEventSourcePage();
        $destinationSourcePage = $this->getCurrentEventSourcePage();

        return $originSourcePage < $destinationSourcePage ? 1 : (
        $originSourcePage == $destinationSourcePage ? null : -1
        );
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function ICE_IsEnteringReviewedState() {
        return (
                        $this->old_translation->isTranslationStatus() &&
                        $this->wanted_translation->isReviewedStatus() &&
                        $this->isAlreadyModifiedIce()
                )
                ||
                (
                        // we are moving an ICE from R1 to R2
                        $this->old_translation->isICE() &&
                        $this->old_translation->isReviewedStatus() &&
                        $this->wanted_translation->isReviewedStatus() &&
                        $this->getPreviousEventSourcePage() == $this->getCurrentEventSourcePage() &&
                        $this->getCurrentEventSourcePage() > Constants::SOURCE_PAGE_REVISION
                );
    }

    /**
     * This should happen only with Ebay because is not possible anymore to change the status to "translated" from rev1 page
     *
     * WHEN we are changing an ICE 'APPROVED' to TRANSLATE
     * AND we are in Revision 1 ( NOT Revision 2 )
     * AND the content IS changed FOR THE FIRST TIME ( old version_number == 0 )
     *
     *
     * @return bool
     * @throws Exception
     */
    public function isModifyingICEFromTranslationForTheFirstTime() {
        return $this->old_translation->isICE() &&
                $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isTranslationStatus() &&
                $this->getPreviousEventSourcePage() == Constants::SOURCE_PAGE_REVISION &&
                $this->old_translation->translation !== $this->wanted_translation->translation &&
                $this->old_translation->version_number == 0;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isModifyingICEFromRevisionOne() {
        return $this->old_translation->isICE() &&
                $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isReviewedStatus() && // This is the trick, the segment is passing from approved to approved
                $this->getPreviousEventSourcePage() == Constants::SOURCE_PAGE_REVISION &&
                $this->old_translation->translation !== $this->wanted_translation->translation &&
                $this->old_translation->version_number == 0;
    }

    /**
     * We need to know if the record is an umodified ICE
     * Unmodified ICEs are locked ICEs which have new version number equal to 0.
     *
     * @return bool
     */
    protected function isAlreadyModifiedIce() {
        return $this->old_translation->isICE() &&               // segment is ICE
                $this->wanted_translation->version_number > 0   // version number is not changing
                ;
    }

    /**
     * @return bool
     */
    public function isFirstTimeIceChange() {
        return ( $this->old_translation->isICE() &&
                $this->old_translation->version_number == 0 &&
                $this->wanted_translation->version_number == 1
        );
    }

    /**
     * Exits reviewed state when it's not editing an ICE for the first time.
     * @return bool
     */
    public function isExitingReviewedState() {
        return $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isTranslationStatus() &&
                !$this->isFirstTimeIceChange() &&
                !$this->isChangingICEtoTranslatedWithNoChange();
    }

    protected function isChangingICEtoTranslatedWithNoChange() {
        return $this->old_translation->isICE() &&
                $this->wanted_translation->isTranslationStatus() &&
                $this->old_translation->isReviewedStatus() &&
                $this->old_translation->version_number == $this->wanted_translation->version_number;
    }

    /**
     * @return Segments_SegmentStruct
     */
    public function getSegmentStruct() {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkIdAndSegmentId(
                $this->chunk->id,
                $this->chunk->password,
                $this->wanted_translation->id_segment
        );
    }

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk() {
        return $this->chunk;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isEditingCurrentRevision() {
        return $this->getCurrentEventSourcePage() == $this->getPreviousEventSourcePage() &&
                $this->wanted_translation->translation != $this->old_translation->translation;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isUpperRevision() {
        return $this->getPreviousEventSourcePage() < $this->getCurrentEventSourcePage();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isLowerRevision() {
        return $this->getPreviousEventSourcePage() > $this->getCurrentEventSourcePage();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isChangingSourcePage() {
        return $this->getPreviousEventSourcePage() != $this->getCurrentEventSourcePage();
    }

    public function isPersisted() {
        return isset( $this->current_event ) && !is_null( $this->current_event->id );
    }

    /**
     * @throws ValidationError
     * @throws Exception
     */
    public function save() {

        if ( isset( $this->current_event ) ) {
            throw new Exception( 'The current event was persisted already. Use getCurrentEvent to retrieve it.' );
        }

        if (
                in_array( $this->wanted_translation[ 'status' ], Constants_TranslationStatus::$REVISION_STATUSES ) &&
                $this->source_page < Constants::SOURCE_PAGE_REVISION
        ) {
            throw new ValidationError( 'Setting revised state from translation is not allowed.', -2000 );
        }

        if (
                in_array( $this->wanted_translation[ 'status' ], Constants_TranslationStatus::$TRANSLATION_STATUSES ) &&
                $this->source_page >= Constants::SOURCE_PAGE_REVISION
        ) {
            throw new ValidationError( 'Setting translated state from revision is not allowed.', -2000 );
        }

        if ( !$this->_saveRequired() ) {
            return;
        }

        $this->current_event                 = new TranslationEventStruct();
        $this->current_event->id_job         = $this->wanted_translation[ 'id_job' ];
        $this->current_event->id_segment     = $this->wanted_translation[ 'id_segment' ];
        $this->current_event->uid            = ( $this->user->uid != null ? $this->user->uid : 0 );
        $this->current_event->status         = $this->wanted_translation[ 'status' ];
        $this->current_event->version_number = $this->wanted_translation[ 'version_number' ];
        $this->current_event->source_page    = $this->source_page;

        if ( $this->isPropagationSource() ) {
            $this->current_event->time_to_edit = $this->wanted_translation[ 'time_to_edit' ];
        }

        $this->current_event->setTimestamp( 'create_date', time() );

        $this->current_event->id = TranslationEventDao::insertStruct( $this->current_event );

    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function _saveRequired() {
        return (
                $this->old_translation->translation != $this->wanted_translation->translation ||
                $this->old_translation->status != $this->wanted_translation->status ||
                $this->source_page != $this->getPreviousEventSourcePage()
        );
    }

    /**
     * This may return null in some cases because prior event can be missing.
     *
     * @return TranslationEventStruct|null
     */
    public function getPreviousEvent() {
        if ( !isset( $this->previous_event ) ) {
            $this->previous_event = ( new TranslationEventDao() )->getLatestEventForSegment(
                    $this->old_translation->id_job,
                    $this->old_translation->id_segment
            );
        }

        return $this->previous_event;
    }

    /**
     * @return TranslationEventStruct
     * @throws Exception
     */
    public function getCurrentEvent() {
        if ( !isset( $this->current_event ) ) {
            throw new Exception( 'The current segment was not persisted yet. Run save() first.' );
        }

        return $this->current_event;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getPreviousEventSourcePage() {
        if ( !$this->getPreviousEvent() ) {
            if (
                    in_array( $this->getOldTranslation()->status,
                            array_merge(
                                    Constants_TranslationStatus::$TRANSLATION_STATUSES,
                                    Constants_TranslationStatus::$INITIAL_STATUSES
                            ) )
            ) {
                $source_page = Constants::SOURCE_PAGE_TRANSLATE;
            } elseif ( Constants_TranslationStatus::isReviewedStatus( $this->getOldTranslation()->status ) ) {
                $source_page = Constants::SOURCE_PAGE_REVISION;
            } else {
                throw new Exception( 'Unable to guess source_page for missing prior event' );
            }

            return $source_page;
        } else {
            return $this->getPreviousEvent()->source_page;
        }
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getCurrentEventSourcePage() {
        return $this->getCurrentEvent()->source_page;
    }

    public function isPropagationSource() {
        return $this->_isPropagationSource;
    }

    public function setPropagationSource( $value ) {
        $this->_isPropagationSource = $value;
    }

}