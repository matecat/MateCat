<?php

namespace Translations;


/**
 * Class WarningModel
 *
 * This class handles interactions with translation warnings, taking care to
 * merge the wanring fields on segment_translations so to make it consistent
 * with the severity of warnings being saved.
 *
 * TODO: ensure it handles also legacy warnings, setting the field to 1 when
 * when necessary.
 *
 * 
 * @package Translations
 */
class WarningModel {

    const ERROR = 1 ;
    const WARNING = 2 ;
    const NOTICE = 4 ;
    const INFO = 8 ;
    const DEBUG = 16 ;

    private $id_job ;
    private $id_segment ;

    private $started = false;
    private $queue = array() ;

    private $scope = null ;

    private $severity ;

    /**
     * @var \Translations_SegmentTranslationStruct
     */
    private $translation ;

    public function __construct( $id_job, $id_segment ) {
        $this->id_job = $id_job ;
        $this->id_segment = $id_segment ;
    }

    /**
     * start
     *
     * Preparatory setup necessary in order to call save() later.
     */
    public function start() {
        $this->started = true ;
        $this->queue = array() ;
        $this->scope = null;
        $this->resetSeverity() ;
        return $this;
    }

    /**
     * resetScope
     *
     * When this function is invoked, the scope for current id_segment is reset.
     * This is done deleting all warnings matching the given scope.
     *
     * @param $scope string of the scope to delete before warnings are inserted.
     * @return $this
     */
    public function resetScope($scope) {
        $this->scope = $scope ;
        return $this;
    }

    /**
     * addWarning
     *
     * Add warning to the queue for later insert and merge severity with the current value.
     * @param WarningStruct $warning
     * @return $this
     */
    public function addWarning( WarningStruct $warning ) {
        $this->queue[] = $warning ;
        $this->severity = $this->severity | $warning->severity ;
        return $this;
    }


    /**
     * save
     *
     * Saves the queued warnings, taking care of doing a reset of the scope if necessary, and
     * to update the warning field on the segment_translations.
     * 
     */
    public function save() {
        if ( $this->scope != null ) {
            WarningDao::deleteByScope( $this->id_job, $this->id_segment, $this->scope) ;
        }

        foreach($this->queue as $warning) {
            WarningDao::insertWarning( $warning ) ;
        }

        \Translations_SegmentTranslationDao::updateSeverity( $this->translation, $this->severity );

    }

    private function resetSeverity() {
        $this->translation = \Translations_SegmentTranslationDao::findBySegmentAndJob( $this->id_segment, $this->id_job );
        $this->severity = $this->translation->warning ;
    }



}