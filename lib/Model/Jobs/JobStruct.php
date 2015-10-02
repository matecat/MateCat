<?php

class Jobs_JobStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
  public $id;
  public $password;
  public $id_project ;
  public $create_date ;

  public $job_first_segment ;
  public $job_last_segment ;

  public $source ;
  public $target ;
  public $tm_keys ;

  public function getFile() {
    return Files_FileDao::getByJobId( $this->id );
  }

  public function getProject() {
    return Projects_ProjectDao::findById( $this->id_project );
  }

  public function isFeatureEnabled( $feature_code ) {
    return $this->getProject()->isFeatureEnabled( $feature_code ) ;
  }

  public function findLatestTranslation() {
    $dao = new Translations_SegmentTranslationDao( Database::obtain() );
    return $dao->lastTranslationByJobOrChunk( $this );
  }

  public function getChunks() {
    $dao = new Chunks_ChunkDao( Database::obtain() );
    return $dao->getByProjectId( $this->id_project );
  }

}
