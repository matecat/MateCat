<?php

class Segments_SegmentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

  public $id;
  public $id_file ;
  public $id_file_part ;
  public $internal_id ;
  public $xliff_mrk_id ;
  public $xliff_ext_prec_tags ;
  public $xliff_mrk_ext_prec_tags ;
  public $segment ;
  public $segment_hash ;
  public $xliff_mrk_ext_succ_tags ;
  public $xliff_ext_succ_tags ;
  public $raw_word_count;
  public $show_in_cattool ;


}
