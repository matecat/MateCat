<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/05/2017
 * Time: 18:45
 */

namespace Features\ReviewImproved\Model;

use Chunks_ChunkStruct;

class ArchivedQualityReportModel {

    /** * @var Chunks_ChunkStruct */
    protected $chunk ;

    /** @var  QualityReportModel */
    protected $report ;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk  ;
    }

    /**
     * @return QualityReportModel
     */
    public function getQualityReport() {
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
        $struct = new ArchivedQualityReportStruct();
        $struct->quality_report = json_encode( $this->getQualityReport()->getStructure() ) ;

        $struct->password          = $this->chunk->password ;
        $struct->id_job            = $this->chunk->id ;
        $struct->job_first_segment = $this->chunk->job_first_segment ;
        $struct->job_last_segment  = $this->chunk->job_last_segment ;
        $struct->id_project        = $this->chunk->id_project ;
        $struct->created_by        = $uid ;

        $dao = new ArchivedQualityReportDao() ;
        $struct->version = $dao->getLastVersionNumber( $this->chunk ) + 1 ;
        $result = $dao->archiveQualityReport( $struct );

        if ( $result ) {
            $this->chunk->getProject()->getFeatures()->run('archivedQualityReportSaved', $this);
        }
    }

}