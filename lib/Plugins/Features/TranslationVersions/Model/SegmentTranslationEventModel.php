<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:34
 */

namespace Features\TranslationVersions\Model;

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


    public function __construct( Translations_SegmentTranslationStruct $old_translation,
                                 Translations_SegmentTranslationStruct $translation,
                                 $user, $source_page_code) {

        $this->old_translation  = $old_translation ;
        $this->translation      = $translation ;
        $this->user             = $user ;
        $this->source_page_code = $source_page_code ;
    }

    public function setPropagatedIds( $propagated_ids ) {
        $this->propagated_ids = $propagated_ids ;
    }

    public function save() {

        if ( !$this->_saveRequired() ) {
            return ;
        }

        $this->openTransaction() ;

        $struct                 = new SegmentTranslationEventStruct() ;
        $struct->id_job         = $this->translation['id_job'] ;
        $struct->id_segment     = $this->translation['id_segment'] ;
        $struct->uid            = ( $this->user->uid != null ? $this->user->uid : 0 );
        $struct->status         = $this->translation['status'] ;
        $struct->version_number = $this->translation['version_number'] ;
        $struct->source_page    = $this->source_page_code ;

        $struct->setTimestamp('create_date', time() );

        SegmentTranslationEventDao::insertStruct( $struct ) ;

        if ( ! empty( $this->propagated_ids ) ) {
            $dao = new SegmentTranslationEventDao();
            $dao->insertForPropagation($this->propagated_ids, $struct);
        }

        $this->commitTransaction() ;
    }

    /**
     * @return bool
     */
    protected function _saveRequired() {

        return (
                $this->old_translation->translation != $this->translation->translation ||
                $this->old_translation->status      != $this->translation->status ||
                $this->source_page_code != $this->_getLatestEventSourcePageCode()
        );
    }

    /**
     * Returns the source_page code of the latest event of the given segment
     *
     * @return null
     */
    protected function _getLatestEventSourcePageCode() {
        $latestEvent = ( new SegmentTranslationEventDao())->getLatestEventIdsByJob(
                $this->old_translation->id_job,
                $this->old_translation->id_segment,
                $this->old_translation->id_segment
        ) ;

        if ( count( $latestEvent ) ) {
            return $latestEvent[0]->source_page ;
        } else {
            return null ;
        }
    }

}