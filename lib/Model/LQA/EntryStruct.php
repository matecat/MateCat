<?php

namespace LQA;

class EntryStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {
    public $id                  ;
    public $uid                 ;
    public $id_segment          ;
    public $id_job              ;
    public $id_category         ;
    public $severity            ;
    public $translation_version ;
    public $start_position      ;
    public $stop_position       ;
    public $is_full_segment     ;
    public $penalty_points      ;
    public $comment             ;
    public $create_date         ;
    public $target_text         ;
    public $category            ;

}
