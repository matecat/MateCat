<?php

namespace LQA ;

class JobReviewStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $id_project ;
    public $id_job ;
    public $password ;
    public $review_password ;
    public $score ;
    public $num_errors ;
    public $is_pass ;
    public $force_pass_at ;

}
