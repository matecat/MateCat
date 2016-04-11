<?php

class Files_FileStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
  public $id  ;
  public $id_project  ;
  public $filename ;
  public $source_language ;
  public $mime_type ;
  public $xliff_file ;
  public $sha1_original_file ;
  public $original_file ;
  public $segmentation_rule;


}
