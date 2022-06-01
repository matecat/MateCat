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
    protected $translation;

    protected $user;

    protected $source_page;

    /**
     * @var TranslationEventStruct
     */
    protected $prior_event;

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

        $this->old_translation = $old_translation;
        $this->translation     = $translation;
        $this->user            = $user;
        $this->source_page     = $source_page_code;
        $this->chunk           = $this->translation->getChunk();

        $this->getPriorEvent();
    }


    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getTranslation() {
        return $this->translation;
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
                $this->translation->isReviewedStatus() &&
                $this->isUpperRevision();
    }

    public function isBeingLowerReviewed() {
        return $this->old_translation->isReviewedStatus() &&
                $this->translation->isReviewedStatus() &&
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
        $originSourcePage      = $this->getOriginSourcePage();
        $destinationSourcePage = $this->getDestinationSourcePage();

        return $originSourcePage < $destinationSourcePage ? 1 : (
        $originSourcePage == $destinationSourcePage ? null : -1
        );
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isEnteringReviewedState() {
        return (
                        $this->old_translation->isTranslationStatus() &&
                        $this->translation->isReviewedStatus() &&
                        !$this->isUnmodifiedICE()
                )
                ||
                (
                        $this->old_translation->isReviewedStatus() &&
                        $this->translation->isReviewedStatus() &&
                        $this->isModifyingICEFromTranslation()
                );
    }

    /**
     * This method returns true when we are changing and ICE 'APPROVED' to TRANSLATE
     *
     * @return bool
     * @throws Exception
     */
    public function isModifyingICEFromTranslation() {
        return $this->old_translation->isICE() &&
                $this->old_translation->isReviewedStatus() &&
                $this->translation->isTranslationStatus() &&
                $this->getOriginSourcePage() == Constants::SOURCE_PAGE_REVISION &&
                $this->old_translation->translation !== $this->translation->translation &&
                $this->old_translation->version_number == 0;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isModifyingICEFromRevisionOne() {
        return $this->old_translation->isICE() &&
                $this->old_translation->isReviewedStatus() &&
                $this->translation->isReviewedStatus() && // This is the trick, the segment is passing from approved to approved
                $this->getOriginSourcePage() == Constants::SOURCE_PAGE_REVISION &&
                $this->old_translation->translation !== $this->translation->translation &&
                $this->old_translation->version_number == 0;
    }

    /**
     * We need to know if the record is an umodified ICE
     * Unmodified ICEs are locked ICEs which have new version number equal to 0.
     *
     * @return bool
     */
    protected function isUnmodifiedICE() {
        return $this->old_translation->isICE() &&               // segment is ICE
                $this->translation->version_number == 0 &&      // version number is not changing
                $this->old_translation->locked == 1             // in some cases ICEs are not locked. Only consider locked ICEs
                ;
    }

    /**
     * Exits reviewed state when it's not editing an ICE for the first time.
     * @return bool
     */
    public function isExitingReviewedState() {
        return $this->old_translation->isReviewedStatus() &&
                $this->translation->isTranslationStatus() &&
                !$this->isEditingICEforTheFirstTime() &&
                !$this->_isChangingICEtoTranslatedWithNoChange();
    }

    /**
     * @return bool
     */
    public function isEditingICEforTheFirstTime() {
        return ( $this->old_translation->isICE() &&
                $this->old_translation->version_number == 0 &&
                $this->translation->version_number == 1
        );
    }

    protected function _isChangingICEtoTranslatedWithNoChange() {
        return $this->old_translation->isICE() &&
                $this->translation->isTranslationStatus() &&
                $this->old_translation->isReviewedStatus() &&
                $this->old_translation->version_number == $this->translation->version_number;
    }

    /**
     * @return Segments_SegmentStruct
     */
    public function getSegmentStruct() {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkIdAndSegmentId(
                $this->chunk->id,
                $this->chunk->password,
                $this->translation->id_segment
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
        return $this->getDestinationSourcePage() == $this->getOriginSourcePage() &&
                $this->translation->translation != $this->old_translation->translation;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isUpperRevision() {
        return $this->getOriginSourcePage() < $this->getDestinationSourcePage();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isLowerRevision() {
        return $this->getOriginSourcePage() > $this->getDestinationSourcePage();
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isChangingSourcePage(): bool {
        return $this->getOriginSourcePage() != $this->getDestinationSourcePage();
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
                in_array( $this->translation[ 'status' ], Constants_TranslationStatus::$REVISION_STATUSES ) &&
                $this->source_page < Constants::SOURCE_PAGE_REVISION
        ) {
            throw new ValidationError( 'Setting revised state from translation is not allowed.', -2000 );
        }

        if (
                in_array( $this->translation[ 'status' ], Constants_TranslationStatus::$TRANSLATION_STATUSES ) &&
                $this->source_page >= Constants::SOURCE_PAGE_REVISION
        ) {
            throw new ValidationError( 'Setting translated state from revision is not allowed.', -2000 );
        }

        if ( !$this->_saveRequired() ) {
            return;
        }

        $this->current_event                 = new TranslationEventStruct();
        $this->current_event->id_job         = $this->translation[ 'id_job' ];
        $this->current_event->id_segment     = $this->translation[ 'id_segment' ];
        $this->current_event->uid            = ( $this->user->uid != null ? $this->user->uid : 0 );
        $this->current_event->status         = $this->translation[ 'status' ];
        $this->current_event->version_number = $this->translation[ 'version_number' ];
        $this->current_event->source_page    = $this->source_page;

        if ( $this->isPropagationSource() ) {
            $this->current_event->time_to_edit = $this->translation[ 'time_to_edit' ];
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
                $this->old_translation->translation != $this->translation->translation ||
                $this->old_translation->status != $this->translation->status ||
                $this->source_page != $this->getOriginSourcePage()
        );
    }

    /**
     * This may return null in some cases because prior event can be missing.
     *
     * @return TranslationEventStruct|null
     */
    public function getPriorEvent() {
        if ( !isset( $this->prior_event ) ) {
            $this->prior_event = ( new TranslationEventDao() )->getLatestEventForSegment(
                    $this->old_translation->id_job,
                    $this->old_translation->id_segment
            );
        }

        return $this->prior_event;
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
    public function getOriginSourcePage() {
        if ( !$this->getPriorEvent() ) {
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
                throw new \Exception( 'Unable to guess source_page for missing prior event' );
            }

            return $source_page;
        } else {
            return $this->getPriorEvent()->source_page;
        }
    }

    /**
     * @return int
     */
    public function getDestinationSourcePage() {
        return $this->getCurrentEvent()->source_page;
    }

    public function isPropagationSource() {
        return $this->_isPropagationSource;
    }

    public function setPropagationSource( $value ) {
        $this->_isPropagationSource = $value;
    }

}