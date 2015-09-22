<?php

class Chunks_ChunkCompletionEventStruct extends DataAccess_AbstractDaoSilentStruct {

  const SOURCE_MERGE = 'merge';
  const SOURCE_USER = 'user';

  public $id ;
  public $id_job ;
  public $password ;
  public $uid ;
  public $source ;
  public $first_job_segment ;
  public $last_job_segment ;
  public $create_date ;

}
