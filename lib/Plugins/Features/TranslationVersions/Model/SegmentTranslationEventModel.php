<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:34
 */

namespace Features\TranslationVersions\Model;


class SegmentTranslationEventModel {

    protected $old_translation ;
    protected $translation ;
    protected $user ;
    protected $propagated_ids ;
    protected $source_page_code ;

    public function __construct($old_translation, $translation, $user, $source_page_code) {
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

        $conn = new SegmentTranslationEventDao();
        $conn->getConnection()->begin() ;

        $struct                 = new SegmentTranslationEventStruct() ;
        $struct->id_job         = $this->translation['id_job'] ;
        $struct->id_segment     = $this->translation['id_segment'] ;
        $struct->uid            = ( $this->user->uid != null ? $this->user->uid : 0 );
        $struct->status         = $this->translation['status'] ;
        $struct->version_number = $this->translation['version_number'] ;
        $struct->source_page    = $this->source_page_code ;

        $id = SegmentTranslationEventDao::insertStruct( $struct ) ;

        if ( ! empty( $this->propagated_ids ) ) {
            $dao = new SegmentTranslationEventDao();
            $dao->insertForPropagation($this->propagated_ids, $struct);
        }

        $conn->getConnection()->commit();
    }

    protected function _savePropagatedIds() {
    }

    /**
     * @return bool
     */
    protected function _saveRequired() {
        return (
                $this->old_translation['translation'] != $this->translation['translation'] ||
                $this->old_translation['status']      != $this->translation['status']
                );
    }

}