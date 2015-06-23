<?php

class Comments_CommentDao extends DataAccess_AbstractDao {

  const TABLE = "comments";
  const STRUCT_TYPE = "Comments_CommentStruct";

  const TYPE_COMMENT = 1 ;
  const TYPE_RESOLVE = 2 ;

  public function saveComment( $obj ) {
      $obj = $this->sanitize( $obj );

      $obj->create_date = date( 'Y-m-d H:i:s' ) ;

      if ($obj->message_type == null) {
          $obj->message_type = self::TYPE_COMMENT ;
      }

      $this->validateForComment($obj);

      $query = " INSERT INTO comments " .
          " ( " .
          " id_job, id_segment, create_date, email, full_name, uid, " .
          " user_role, message_type, message ) " .
          " VALUES " .
          " ( "  .
          implode(", ", array(
               $obj->id_job  ,
               $obj->id_segment  ,
               "'$obj->create_date'"  ,
               $obj->email     == null ? "'NULL'" : "'$obj->email'",
               $obj->full_name == null ? "'NULL'" : "'$obj->full_name'",
               $obj->uid       == null ? "NULL"   : $obj->uid ,
               $obj->user_role ,
               $obj->message_type ,
               "'$obj->message'"
          ) ) . " ) ";


      Log::doLog( $query );

      $this->con->query( $query );
      $this->_checkForErrors();

      return $obj ;
  }

  public function resolveThread( $obj ) {
      $obj->message_type = self::TYPE_RESOLVE ;
      $obj->resolve_date  = date('Y-m-d H:i:s');

      $obj = $this->sanitize( $obj );

      $this->con->begin();

      try {
          $new_record = $this->saveComment( $obj );

          $update = "UPDATE comments SET resolve_date = '$obj->resolve_date' " .
              " WHERE id_segment = $obj->id_segment " .
              " AND id_job = $obj->id_job " .
              " AND resolve_date IS NULL " ;

          Log::doLog( $update );

          $this->con->query( $update );
          $this->_checkForErrors();

          $this->con->commit();
      } catch ( Exception $e ) {
          $err = $this->con->get_error();
          Log::doLog( "Error: " . var_export( $err, true ) );
          $this->con->rollback();
      }

      $obj->thread_id = $obj->getThreadId() ;

      return $obj ;

  }

  public function getOpenCommentsInJob( $input ) {
      // sanitize input
      $obj = $this->sanitize( $input );

      $query = $this->finderQuery() .
          " WHERE id_job = $obj->id_job " .
          " AND resolve_date IS NULL " .
          " AND ( id_segment < $obj->first_segment OR id_segment > $obj->last_segment ) " .
          " ORDER BY id_segment ASC, create_date ASC ";

      $this->con->query( $query );

      $arr_result = $this->_fetch_array( $query );

      $this->_checkForErrors();
      return $this->_buildResult( $arr_result );
  }

  public function getCommentsBySegmentsRange( $input ) {
      $obj = $this->sanitize( $input );

      $query = $this->finderQuery() .
          " WHERE id_job = $obj->id_job " .
          " AND resolve_date IS NULL " .
          " AND id_segment >= $obj->first_segment AND id_segment <= $obj->last_segment " .
          " ORDER BY id_segment ASC, create_date ASC ";

      $this->con->query( $query );

      $arr_result = $this->_fetch_array( $query );

      $this->_checkForErrors();
      return $this->_buildResult( $arr_result );
  }

  private function finderQuery() {
      return "SELECT " .
          " id_job, id_segment, create_date, full_name, resolve_date, " .
          " user_role, message_type, message, " .
          " MD5( CONCAT( id_job, '-', id_segment, '-', resolve_date ) ) AS thread_id " .
          " FROM " . self::TABLE ;
  }


  private function validateForComment($obj) {
      if ( empty($obj->message) && $obj->message_type == self::TYPE_COMMENT ) {
          throw new Exception( "Comment message can't be blank." );
      }
  }

  protected function _buildResult( $array_result ) {
      $result = array();

      foreach ( $array_result as $item ) {
          $build_arr = array(
              'id_job'         => $item[ 'id_job' ],
              'id_segment'     => $item[ 'id_segment' ],
              'create_date'    => $item[ 'create_date' ],
              'full_name'      => $item[ 'full_name' ],
              'thread_id'      => $item[ 'thread_id' ],
              'user_role'      => $item[ 'user_role' ],
              'message_type'   => $item[ 'message_type' ],
              'message'        => $item[ 'message' ],
              'formatted_date' => self::formattedDate($item[ 'create_date' ])
          );

          $result[] = $build_arr ;
      }

      return $result ;
  }

  static function formattedDate($time) {
      return strftime('%l:%M %p %e %b %Y', strtotime( $time ) ) ;
  }

  public function sanitize( $input ) {
      parent::_sanitizeInput( $input, self::STRUCT_TYPE );


      $input->id_job       = self::intWithNull( $input->id_job );
      $input->id_segment   = self::intWithNull( $input->id_segment );
      $input->uid          = self::intWithNull( $input->uid );
      $input->user_role    = self::intWithNull( $input->user_role );
      $input->message_type = self::intWithNull( $input->message_type );

      $input->first_segment = self::intWithNull( $input->first_segment );
      $input->last_segment  = self::intWithNull( $input->last_segment );

      $input->message      = self::escapeWithNull( trim( $input->message ) );
      $input->email        = self::escapeWithNull( $input->email );
      $input->full_name    = self::escapeWithNull( $input->full_name );

      return $input ;
  }

  private static function escapeWithNull($value) {
      $conn = self::getConnection();
      if ( $value !== null ) {
          return $conn->escape( $value );
      }
      else {
          return null ;
      }
  }

  private static function intWithNull( $value ) {
      if ( $value === null ) {
          return null  ;
      } else {
          return (int) $value ;
      }
  }

  private static function getConnection() {
      // TODO: find connection configuration here, because comments
      // may reside on a different database
      //
      return Database::obtain();
  }


}
