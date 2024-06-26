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

        $this->getLatestEventForSegment();
    }


    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getWantedTranslation(): Translations_SegmentTranslationStruct {
        return $this->wanted_translation;
    }

    /**
     * @return Users_UserStruct|null
     * @throws Exception
     */
    public function getEventUser(): ?Users_UserStruct {
        if ( $this->getCurrentEvent()->uid ) {
            return ( new Users_UserDao() )->getByUid( $this->getCurrentEvent()->uid );
        }
        return null;
    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws Exception
     */
    public function getOldTranslation(): Translations_SegmentTranslationStruct {
        if ( is_null( $this->old_translation ) ) {
            throw new Exception( 'Old translation is not set' );
        }

        return $this->old_translation;
    }

    public function isADraftChange(): bool {
        return $this->statusAsSourcePage( $this->wanted_translation->status ) == 0;
    }

    public function isChangingStatus(): bool {
        return $this->old_translation->status !== $this->wanted_translation->status;
    }

    public function isIce(): bool {
        return $this->old_translation->isICE();
    }

    public function isUnModifiedIce(): bool {
        return $this->isIce() &&
                $this->old_translation->version_number == 0 &&
                $this->wanted_translation->version_number == 0;
    }

    /**
     * @return Segments_SegmentStruct
     */
    public function getSegmentStruct(): ?Segments_SegmentStruct {
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
    public function getChunk(): ?Chunks_ChunkStruct {
        return $this->chunk;
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return bool
     * @throws Exception
     */
    public function currentEventIsOnThisChunk( ChunkReviewStruct $chunkReview ): bool {
        return $this->getCurrentEventSourcePage() == $chunkReview->source_page;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isLowerTransition(): bool {
        return $this->statusAsSourcePage( $this->old_translation->status ) > $this->statusAsSourcePage( $this->wanted_translation->status );
    }

    /**
     * @return bool
     */
    public function isPersisted(): bool {
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
        $this->current_event->version_number = ( $this->wanted_translation[ 'version_number' ] != null ? $this->wanted_translation[ 'version_number' ] : 0 );
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
    protected function _saveRequired(): bool {
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
    public function getLatestEventForSegment(): ?TranslationEventStruct {
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
    public function getCurrentEvent(): TranslationEventStruct {
        if ( !isset( $this->current_event ) ) {
            throw new Exception( 'The current segment was not persisted yet. Run save() first.' );
        }

        return $this->current_event;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getPreviousEventSourcePage(): int {
        if ( !$this->getLatestEventForSegment() ) {
            if (
                    in_array( $this->getOldTranslation()->status,
                            array_merge(
                                    Constants_TranslationStatus::$TRANSLATION_STATUSES,
                                    Constants_TranslationStatus::$INITIAL_STATUSES
                            ) )
            ) {
                $source_page = Constants::SOURCE_PAGE_TRANSLATE;
            } elseif ( $this->getOldTranslation()->status == Constants_TranslationStatus::STATUS_APPROVED ) {
                $source_page = Constants::SOURCE_PAGE_REVISION;
            } elseif ( $this->getOldTranslation()->status == Constants_TranslationStatus::STATUS_APPROVED2 ) {
                $source_page = Constants::SOURCE_PAGE_REVISION_2;
            } else {
                throw new Exception( 'Unable to guess source_page for missing prior event' );
            }

            return $source_page;
        } else {
            return $this->getLatestEventForSegment()->source_page;
        }
    }

    /**
     * @param $status
     *
     * @return int
     */
    public function statusAsSourcePage( $status ): int {

        switch ( $status ) {
            case $status == Constants_TranslationStatus::STATUS_TRANSLATED:
                return Constants::SOURCE_PAGE_TRANSLATE;
            case $status == Constants_TranslationStatus::STATUS_APPROVED:
                return Constants::SOURCE_PAGE_REVISION;
            case $status == Constants_TranslationStatus::STATUS_APPROVED2:
                return Constants::SOURCE_PAGE_REVISION_2;
            default:
                return 0;
        }

    }

    /**
     * @return int
     * @throws Exception
     */
    public function getCurrentEventSourcePage(): int {
        return $this->getCurrentEvent()->source_page;
    }

    /**
     * @return bool
     */
    public function isPropagationSource(): bool {
        return $this->_isPropagationSource;
    }

    /**
     * @param bool $value
     *
     * @return void
     */
    public function setPropagationSource( bool $value ): void {
        $this->_isPropagationSource = $value;
    }

}