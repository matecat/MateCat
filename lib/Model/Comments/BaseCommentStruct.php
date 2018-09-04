<?php


class Comments_BaseCommentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $id_job;
    public $id_segment;
    public $create_date;
    public $email;
    public $full_name;
    public $uid;
    public $resolve_date;
    public $source_page;
    public $message_type;
    public $message;

    public function getThreadId() {
        return md5($this->id_job . '-' . $this->id_segment . '-' . $this->resolve_date);
    }

    public function isComment() {
        return ((int) $this->message_type == Comments_CommentDao::TYPE_COMMENT);
    }

    public function templateMessage(){
        $this->message = \Comments_CommentDao::placeholdContent($this->message);
    }

}