<?php

namespace LQA;
class EntryCommentStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $uid ;
    public $id_qa_entry ;
    public $create_date ;
    public $comment ;
    public $source_page ;
}
