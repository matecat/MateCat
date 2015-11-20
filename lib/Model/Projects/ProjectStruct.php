<?php

class Projects_ProjectStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $password ;
    public $id_customer ;
    public $create_date ;
    public $id_engine_tm ;
    public $id_engine_mt ;
    public $status_analysis ;
    public $fast_analysis_wc ;
    public $standard_analysis_wc ;
    public $remote_ip_address ;
    public $for_debug ;
    public $pretranslate_100 ;

    protected $cached_results = array();
    // protected static $cachables = array('getJobs') ;

    public function getOwnerFeature( $feature_code ) {
      return OwnerFeatures_OwnerFeatureDao::getByOwnerEmailAndCode(
        $feature_code, $this->id_customer
      );
    }

    public function getJobs() {
      return $this->cachable(__function__, $this->id, function($id) {
        return Jobs_JobDao::getByProjectId( $id );
      });
    }

    public function getMetadata() {
      return Projects_MetadataDao::getByProjectId( $this->id );
    }

    public function isFeatureEnabled( $feature_code ) {
      return $this->getOwnerFeature( $feature_code ) !== false ;
    }

    public function getChunks() {
      $dao = new Chunks_ChunkDao( Database::obtain() );
      return $dao->getByProjectID( $this->id );
    }

    public function isMarkedComplete() {
      return Chunks_ChunkCompletionEventDao::isProjectCompleted( $this );
    }
}
