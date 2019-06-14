<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:34
 */

namespace Features\TranslationVersions\Model;

use Constants;
use Constants_TranslationStatus;
use Exception;
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
    protected $source_page_code ;

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

    public function setPropagatedIds( $propagated_ids ) {
        $this->propagated_ids = $propagated_ids ;
    }

    public function getPropagatedIds() {
        return is_null( $this->propagated_ids ) ? [] : $this->propagated_ids ;
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

        $this->current_event->setTimestamp('create_date', time() );

        $this->current_event->id = SegmentTranslationEventDao::insertStruct( $this->current_event ) ;

        if ( ! empty( $this->propagated_ids ) ) {
            $dao = new SegmentTranslationEventDao();
            $dao->insertForPropagation($this->propagated_ids, $this->current_event);
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
        if ( $this->current_event == -1 ) {
            throw new Exception('The current segment was not persisted yet. Run save() first.');
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