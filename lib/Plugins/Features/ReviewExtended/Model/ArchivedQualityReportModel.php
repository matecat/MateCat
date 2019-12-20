<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/05/2017
 * Time: 18:45
 */

namespace Features\ReviewExtended\Model;

use Chunks_ChunkStruct;

class ArchivedQualityReportModel {

    /** * @var Chunks_ChunkStruct */
    protected $chunk ;

    /** @var  QualityReportModel */
    protected $report ;

    /**
     * @var ArchivedQualityReportStruct
     */
    protected $archivedRecord;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk  ;
    }

    /**
     * @return QualityReportModel
     */
    protected function getQualityReport() {
        if ( is_null( $this->report ) ) {
            $this->report = new QualityReportModel( $this->chunk );
        }
        return $this->report ;
    }

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk() {
        return $this->chunk ;
    }

    public function saveWithUID( $uid ) {
        $this->archivedRecord                 = new ArchivedQualityReportStruct();
        $this->archivedRecord->quality_report = json_encode( $this->getQualityReport()->getStructure() ) ;

        $this->archivedRecord->password          = $this->chunk->password ;
        $this->archivedRecord->id_job            = $this->chunk->id ;
        $this->archivedRecord->job_first_segment = $this->chunk->job_first_segment ;
        $this->archivedRecord->job_last_segment  = $this->chunk->job_last_segment ;
        $this->archivedRecord->id_project        = $this->chunk->id_project ;
        $this->archivedRecord->created_by        = $uid ;

        $dao = new ArchivedQualityReportDao() ;

        $this->archivedRecord->version = $dao->getLastVersionNumber( $this->chunk ) + 1 ;

        $result = $dao->archiveQualityReport( $this->archivedRecord );

        if ( $result ) {
            $this->chunk->getProject()->getFeaturesSet()->run('archivedQualityReportSaved', $this);
        }
    }

    public function getSavedRecord() {
        return $this->archivedRecord ;
    }


}