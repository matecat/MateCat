<?php

namespace Features\TranslationVersions\Model;

use Constants;
use Constants_TranslationStatus;
use Exception;
use Exceptions\ValidationError;
use LQA\ChunkReviewStruct;
use TransactionableTrait;
use Translations_SegmentTranslationStruct;

class SegmentTranslationEventModel {

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
     * @var SegmentTranslationEventStruct
     */
    protected $prior_event;

    /**
     * @var SegmentTranslationEventStruct
     */
    protected $current_event;

    protected $_isPropagationSource = true;

    public function __construct( Translations_SegmentTranslationStruct $old_translation,
                                 Translations_SegmentTranslationStruct $translation,
                                                                       $user, $source_page_code ) {

        $this->old_translation = $old_translation;
        $this->translation     = $translation;
        $this->user            = $user;
        $this->source_page     = $source_page_code;

        $this->getPriorEvent();
    }

    /**
     * @return bool
     */
    public function isUpperRevision() {
        return $this->getOriginSourcePage() < $this->getDestinationSourcePage();
    }

    /**
     * @return bool
     */
    public function isLowerRevision() {
        return $this->getOriginSourcePage() > $this->getDestinationSourcePage();
    }

    /**
     * @return bool
     */
    public function isChangingSourcePage() {
        return $this->getOriginSourcePage() != $this->getDestinationSourcePage();
    }

    public function isPersisted() {
        return isset( $this->current_event ) && !is_null( $this->current_event->id );
    }

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

        $this->current_event                 = new SegmentTranslationEventStruct();
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

        $this->current_event->id = SegmentTranslationEventDao::insertStruct( $this->current_event );

    }

    /**
     * @return bool
     */
    protected function _saveRequired() {
        return (
                $this->old_translation->translation != $this->translation->translation ||
                $this->old_translation->status != $this->translation->status ||
                $this->source_page != $this->getOriginSourcePage()
        );
    }

    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getOldTranslation() {
        return $this->old_translation;
    }

    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getTranslation() {
        return $this->translation;
    }

    /**
     * This may return null in some cases because prior event can be missing.
     *
     * @return SegmentTranslationEventStruct|null
     */
    public function getPriorEvent() {
        if ( !isset( $this->prior_event ) ) {
            $this->prior_event = ( new SegmentTranslationEventDao() )->getLatestEventForSegment(
                    $this->old_translation->id_job,
                    $this->old_translation->id_segment
            );
        }

        return $this->prior_event;
    }

    /**
     * @return SegmentTranslationEventStruct
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