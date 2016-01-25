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
    public $id_qa_model ;

    public function getOwnerFeature( $feature_code ) {
        $ret = OwnerFeatures_OwnerFeatureDao::getByOwnerEmailAndCode(
            $feature_code, $this->id_customer
        );

        return $ret ;

        // return OwnerFeatures_OwnerFeatureDao::getByOwnerEmailAndCode(
        //     $feature_code, $this->id_customer
        // );
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
        $feature = $this->getOwnerFeature( $feature_code );
        return \Features::enabled($feature, $this);
    }

    public function getChunks() {
      $dao = new Chunks_ChunkDao( Database::obtain() );
      return $dao->getByProjectID( $this->id );
    }

    public function isMarkedComplete() {
      return Chunks_ChunkCompletionEventDao::isProjectCompleted( $this );
    }

    /**
     * @return \LQA\ModelStruct
     *
     */
    public function getLqaModel() {
        return $this->cachable(__METHOD__, $this->id_qa_model, function($id_qa_model) {
            return \LQA\ModelDao::findById( $id_qa_model ) ;
        });
    }

}
