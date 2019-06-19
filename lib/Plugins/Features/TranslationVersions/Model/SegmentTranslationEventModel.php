<?php

namespace Features\TranslationVersions\Model;

use Constants;
use Constants_TranslationStatus;
use Exception;
use Exceptions\ValidationError;
use TransactionableTrait;
use Translations_SegmentTranslationStruct;

class SegmentTranslationEventModel  {
    use TransactionableTrait ;

    /**
     * @var Translations_SegmentTranslationStruct
     */
    protected $old_translation ;

    /**
     * @var Translations_SegmentTranslationStruct
     */
    protected $translation ;

    protected $user ;
    protected $propagated_ids ;

    /**
     * @var Translations_SegmentTranslationStruct[]
     */
    protected $propagated_segments = [] ;

    protected $source_page_code ;

    protected $propagated_events = [] ;

    /**
     * @var int|SegmentTranslationEventStruct
     */
    protected $prior_event = -1 ;

    /**
     * @var int|SegmentTranslationEventStruct
     */
    protected $current_event = -1 ;

    public function __construct( Translations_SegmentTranslationStruct $old_translation,
                                 Translations_SegmentTranslationStruct $translation,
                                 $user, $source_page_code) {

        $this->old_translation  = $old_translation ;
        $this->translation      = $translation ;
        $this->user             = $user ;
        $this->source_page_code = $source_page_code ;

        $this->getPriorEvent() ;
    }

    public function setPropagatedSegments( $propagated_segments ) {
        $this->propagated_segments = $propagated_segments ;
    }

    public function getPropagatedIds() {
        $ids = array_filter(array_map(function($propagated_segment) {
            return $propagated_segment->id_segment ;
        }, $this->propagated_segments ) );

        return is_null( $ids ) ? [] : $ids ;
    }

    /**
     * @return bool
     */
    public function isUpperRevision() {
        return $this->getOriginSourcePage() < $this->getDestinationSourcePage() ;
    }

    /**
     * @return bool
     */
    public function isLowerRevision() {
        return $this->getOriginSourcePage() > $this->getDestinationSourcePage() ;
    }

    /**
     * @return bool
     */
    public function isChangingSourcePage() {
        return $this->getOriginSourcePage() != $this->getDestinationSourcePage() ;
    }

    public function save() {

        if ( $this->current_event !== -1 ) {
            throw new Exception('The current event was persisted already. Use getCurrentEvent to retrieve it.') ;
        }

        if (
                in_array( $this->translation['status'], Constants_TranslationStatus::$REVISION_STATUSES ) &&
                $this->source_page_code < Constants::SOURCE_PAGE_REVISION
        ) {
            throw new ValidationError('Setting revised state from translation is not allowed.', -2000 );
        }

        if ( !$this->_saveRequired() ) {
            return ;
        }

        $this->openTransaction() ;

        $this->current_event                 = new SegmentTranslationEventStruct() ;
        $this->current_event->id_job         = $this->translation['id_job'] ;
        $this->current_event->id_segment     = $this->translation['id_segment'] ;
        $this->current_event->uid            = ( $this->user->uid != null ? $this->user->uid : 0 );
        $this->current_event->status         = $this->translation['status'] ;
        $this->current_event->version_number = $this->translation['version_number'] ;
        $this->current_event->source_page    = $this->source_page_code ;

        if ( $this->isEdit() ) {
            $this->current_event->time_to_edit   = $this->translation['time_to_edit'];
        }

        $this->current_event->setTimestamp('create_date', time() );

        $this->current_event->id = SegmentTranslationEventDao::insertStruct( $this->current_event ) ;

        if ( ! empty( $this->propagated_segments ) ) {
            foreach( $this->propagated_segments as $segment ) {
                $structForPropagatedEvent                 = clone $this->current_event ;
                $structForPropagatedEvent->id             = null ;
                $structForPropagatedEvent->id_segment     = $segment->id_segment ;
                $structForPropagatedEvent->version_number = $segment->version_number ;

                $structForPropagatedEvent->id             = SegmentTranslationEventDao::insertStruct( $structForPropagatedEvent ) ;
                $this->propagated_events[]                = $structForPropagatedEvent ;
            }
        }

        $this->translation->getChunk()
                ->getProject()
                ->getFeatures()
                ->run('translationEventSaved', $this );

        $this->commitTransaction() ;
    }

    /**
     * @return bool
     */
    protected function _saveRequired() {
        return (
                $this->old_translation->translation != $this->translation->translation ||
                $this->old_translation->status      != $this->translation->status ||
                $this->source_page_code             != $this->getOriginSourcePage()
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

    public function isEdit() {
        return $this->translation->translation != $this->old_translation->translation ;
    }

    public function getPropagatedEvents() {
        return $this->propagated_events ;
    }

    /**
     * This may return null in some cases because prior event can be missing.
     *
     * @return SegmentTranslationEventStruct|int|null
     */
    public function getPriorEvent() {
        if ( $this->prior_event === -1 ) {
            $this->prior_event = ( new SegmentTranslationEventDao() )->getLatestEventForSegment(
                    $this->old_translation->id_job,
                    $this->old_translation->id_segment
            ) ;
        }
        return $this->prior_event ;
    }

    /**
     * @return SegmentTranslationEventStruct
     * @throws Exception
     */
    public function getCurrentEvent() {
        if ( ! $this->current_event ) {
            throw new Exception('The current segment was not persisted yet. Run getPropagatedIdsve() first.');
        }
        return $this->current_event ;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getOriginSourcePage() {
        if ( ! $this->getPriorEvent() ) {
            if (
                    in_array( $this->getOldTranslation()->status,
                            array_merge(
                                    Constants_TranslationStatus::$TRANSLATION_STATUSES,
                                    Constants_TranslationStatus::$INITIAL_STATUSES
                            ) )
            )  {
                $source_page = Constants::SOURCE_PAGE_TRANSLATE ;
            }
            elseif ( Constants_TranslationStatus::isReviewedStatus( $this->getOldTranslation()->status ) ) {
                $source_page = Constants::SOURCE_PAGE_REVISION ;
            }
            else {
                throw new \Exception('Unable to guess source_page for missing prior event') ;
            }
            return $source_page ;
        }
        else {
            return $this->getPriorEvent()->source_page ;
        }
    }

    /**
     * @return int
     */
    public function getDestinationSourcePage() {
        return $this->getCurrentEvent()->source_page ;
    }

}