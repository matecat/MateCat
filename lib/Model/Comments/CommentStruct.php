<?php

class Comments_CommentStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

  // database fields
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
  public $timestamp ;
  public $revision_number ;

  // returned values
  public $formatted_date;
  public $thread_id;
  public $id_client ;
  public $password ;

  // query parameters
  public $first_segment;
  public $last_segment;

  public static function getStruct() {
    return new Comments_CommentStruct();
  }

  public function getFormattedDate() {
    return strftime( '%l:%M %p %e %b %Y UTC', strtotime($this->create_date) );
  }

  public function getThreadId() {
    return md5($this->id_job . '-' . $this->id_segment . '-' . $this->resolve_date);
  }

  public function isComment() {
    return ((int) $this->message_type == Comments_CommentDao::TYPE_COMMENT);
  }

}
