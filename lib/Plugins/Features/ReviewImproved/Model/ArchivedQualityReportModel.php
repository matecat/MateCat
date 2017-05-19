<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/05/2017
 * Time: 18:45
 */

namespace Features\ReviewImproved\Model;

class ArchivedQualityReportModel {

    protected $chunk ;

    public function __construct( \Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk  ;
    }

    public function saveWithUID( $uid ) {
        $report = new QualityReportModel( $this->chunk );

        $struct = new ArchivedQualityReportStruct();
        $struct->quality_report = json_encode( $report->getStructure() ) ;

        $struct->password          = $this->chunk->password ;
        $struct->id_job            = $this->chunk->id ;
        $struct->job_first_segment = $this->chunk->job_first_segment ;
        $struct->job_last_segment  = $this->chunk->job_last_segment ;
        $struct->id_project        = $this->chunk->id_project ;
        $struct->created_by        = $uid ;

        $dao = new ArchivedQualityReportDao() ;
        $struct->version = $dao->getLastVersionNumber( $this->chunk ) + 1 ;
        $dao->archiveQualityReport( $struct );
    }

}