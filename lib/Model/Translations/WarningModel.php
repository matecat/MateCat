<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/05/16
 * Time: 12:21
 */

namespace Translations;


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

    public function start() {
        $this->started = true ;
        $this->queue = array() ;
        $this->scope = null;
        $this->resetSeverity() ;
    }

    public function resetScope($scope) {
        $this->scope = $scope ;
    }

    public function addWarning( WarningStruct $warning ) {
        $this->queue[] = $warning ;
        $this->severity = $this->severity | $warning->severity ;
    }

    private function resetSeverity() {
        $this->translation = \Translations_SegmentTranslationDao::findBySegmentAndJob( $this->id_segment, $this->id_job );
        $this->severity = $this->translation->warning ;
    }

    public function save() {
        if ( $this->scope != null ) {
            WarningDao::deleteByScope( $this->id_job, $this->id_segment, $this->scope) ;
        }
        
        foreach($this->queue as $warning) {
            WarningDao::insertWarning( $warning ) ;
        }

        \Translations_SegmentTranslationDao::updateSeverity( $this->translation, $this->severity );
            
    }


}