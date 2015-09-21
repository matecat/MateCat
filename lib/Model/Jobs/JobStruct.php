<?php

class Jobs_JobStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
  public $id;
  public $password;
  public $id_project ;
  public $create_date ;
  public $last_opened_segment ;
  public $last_update ;
  public $source ;
  public $target ;
  public $tm_keys ;

  public function getProject() {
    return Projects_ProjectDao::findById( $this->id_project );
  }

  public function isFeatureEnabled( $feature_code ) {
    return $this->getProject()->isFeatureEnabled( $feature_code ) ;
  }
}
