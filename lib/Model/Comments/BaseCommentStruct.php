<?php

class Comments_BaseCommentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, JsonSerializable {

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
        return md5( $this->id_job . '-' . $this->id_segment . '-' . $this->resolve_date );
    }

    public function isComment() {
        return ( (int)$this->message_type == Comments_CommentDao::TYPE_COMMENT );
    }

    public function templateMessage() {
        $this->message = Comments_CommentDao::placeholdContent( $this->message );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'id_job' => (int)$this->id_job,
            'id_segment' => (int)$this->id_segment,
            'create_date' => $this->create_date,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'uid' => (int)$this->uid,
            'resolve_date' => $this->resolve_date,
            'source_page' => (int)$this->source_page,
            'message_type' => $this->message_type,
            'message' => $this->message,
        ];
    }
}