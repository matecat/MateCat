<?php

namespace Features\TranslationVersions\Model;

use Chunks_ChunkStruct;
use Constants;
use Constants_TranslationStatus;
use Database;
use Exception;
use Exceptions\ValidationError;
use LQA\ChunkReviewStruct;
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

    public function isBeingLowerReviewed() {
        return $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isReviewedStatus() &&
                $this->isLowerTransition();
    }

    public function isBeingLowerReviewedOrTranslated() {
        return $this->isLowerTransition();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function iceIsAboutToBeReviewedForTheFirstTime() {
        return $this->isIceOrPreTranslated() &&
                $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isReviewedStatus() &&
                // we are moving an ICE directly from R1 or R2 ( previous sourcePage is equal to current )
                // or from its initial status
                // because those 2 values are equals even if an ice is approved with no modifications for the first time ( no previous event )
                $this->getPreviousEventSourcePage() == $this->getCurrentEventSourcePage() &&
                $this->getCurrentEventSourcePage() >= Constants::SOURCE_PAGE_REVISION;
    }

    public function isIceOrPreTranslated() {
        return $this->old_translation->isICE() || $this->old_translation->isPreTranslated();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isDowngradedToTranslated() {
        return $this->old_translation->isReviewedStatus() &&
                $this->wanted_translation->isTranslationStatus() &&
                $this->isLowerTransition();
    }

    /**
     * @return bool
     */
    public function isModifiedIce() {
        return $this->isIceOrPreTranslated() && // segment is ICE
                $this->wanted_translation->version_number != 0  // version number is changing
                ;
    }

    /**
     * @return bool
     */
    public function iceIsChangingForTheFirstTime() {
        return $this->isIceOrPreTranslated() &&
                $this->old_translation->version_number == 0 &&
                $this->wanted_translation->version_number == 1;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isR1() {
        return $this->isRevisionNumber( 2 );
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isR2() {
        return $this->isRevisionNumber( 3 );
    }

    /**
     * @param $x
     *
     * @return bool
     * @throws Exception
     */
    protected function isRevisionNumber( $x ) {
        return $this->getCurrentEventSourcePage() == $x;
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
                $this->wanted_translation->translation != $this->old_translation->translation &&
                !$this->iceIsChangingForTheFirstTime();
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return bool
     * @throws Exception
     */
    public function lastEventWasOnThisChunk( ChunkReviewStruct $chunkReview ) {
        return $this->getPreviousEventSourcePage() == $chunkReview->source_page;
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return bool
     * @throws Exception
     */
    public function currentEventIsOnThisChunk( ChunkReviewStruct $chunkReview ) {
        return $this->getCurrentEventSourcePage() == $chunkReview->source_page;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isLowerTransition() {
        return $this->getPreviousEventSourcePage() > $this->getCurrentEventSourcePage();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isUpperTransition() {
        return $this->getCurrentEventSourcePage() > $this->getPreviousEventSourcePage();
    }

    /**
     * @return bool
     */
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


        /*
         * This is true IF:
         * - the translation content is different from previous
         * OR
         * - the status is changed
         * OR
         * - the action event happened on a different page than the previous
         *    ( unmodified ICEs always have the previous event source page equals to the actual one )
         */
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