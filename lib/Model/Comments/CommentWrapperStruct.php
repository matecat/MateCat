<?php

class Comments_CommentWrapperStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

  public $id_job ;
  public $id_client ;
  public $password ;
  public $payload ;

  public static function getStruct() {
    return new Comment_CommentWrapperStruct();
  }

  public function getFormattedDate() {
    return strftime( '%l:%M %p %e %b %Y', strtotime($this->create_date) );
  }

}
