<?php
class JobRevisionDataDao {

  const TABLE = "projects";
  const STRUCT_TYPE = "JobRevisionDataStruct";

    /**
     * @var PDOConnection
     */
  private $conn ;

  private $page_size = 100 ;
  private $page = 1 ;

  public function __construct( Database $connection ) {
      $this->conn = $connection->getConnection() ;
  }

  public function getSegments( $job_id, $password, $options=array() ) {
      $jobData = getJobData( $job_id, $password );

      $this->page =  $options['page'] ? $options['page'] : 1 ;

      if ($jobData == null) {
        throw new Exception('Job data not found, should not happen here.') ;
      }

      $main_query = "" .
        " SELECT " .
        " segments.id AS id_segment " .
        " , segments.segment AS source " .
        " , segment_revisions.original_translation AS translator_target " .
        " , segment_translations.translation AS revisor_target " .
        " , segment_translations.status AS status " .
        " , segment_revisions.err_typing " .
        " , segment_revisions.err_translation " .
        " , segment_revisions.err_terminology " .
        " , segment_revisions.err_language " .
        " , segment_revisions.err_style " .
        " FROM segment_revisions " .
        " INNER JOIN segments ON segments.id = segment_revisions.id_segment " .
        " INNER JOIN segment_translations ON segment_translations.id_segment = segments.id " .
        " INNER JOIN files ON segments.id_file = files.id " .
        " INNER JOIN files_job ON files.id = files_job.id_file " .
        " INNER JOIN jobs ON files_job.id_job = jobs.id " .
        " WHERE jobs.id = :job_id AND jobs.password = :password " .
        " AND segment_translations.status IN ('APPROVED', 'REJECTED') " .
        " ORDER BY segments.id ASC " .
        " LIMIT :page_size " .
        " OFFSET :offset " .
        "" ;

      $full_query = "" .
        " SELECT m.* " .
        " , comments.message AS comment_message " .
        " , DATE_FORMAT( comments.create_date, '%Y-%m-%dT%TZ') AS comment_date " .
        " , comments.full_name AS username " .
        " , comments.email AS email " .
        " , DATE_FORMAT( comments.resolve_date, '%Y-%m-%dT%TZ') AS resolve_date " .
        " FROM ( $main_query ) m " .
        " LEFT JOIN comments ON comments.id_segment = m.id_segment " .
        " AND comments.source_page = :source_page_code " ;

      $stmt = $this->conn->prepare( $full_query );
      $stmt->bindValue( ':job_id', $job_id );
      $stmt->bindValue( ':password', $password, PDO::PARAM_STR );
      $stmt->bindValue( ':source_page_code',
          Comments_CommentDao::SOURCE_PAGE_REVISE );

      $this->paginateStatement( $stmt );

      $stmt->execute();

      $result = $stmt->fetchAll();

      return $result ;
  }

  public function getData( $job_id, $password, $options=array() ) {
      $data = getJobData( $job_id, $password );

      $wCounter = new WordCount_Counter();
      $wStruct = $wCounter->initializeJobWordCount( $job_id, $password ) ;

      $jobQA = new Revise_JobQA(
          $job_id,
          $password,
          $wStruct->getTotal()
      );

      $jobQA->retrieveJobErrorTotals();
      $jobQA->evalJobVote();

      $jobVote = $jobQA->getJobVote();
      return array(
          'job_id'          => $job_id,
          'quality_details' => $jobQA->getQaData(),
          'quality_overall' => $jobVote['minText']
      );

  }

  private function paginateStatement( $stmt ) {
      $stmt->bindValue( ':page_size', $this->page_size, PDO::PARAM_INT );
      $stmt->bindValue( ':offset', ( $this->page - 1 ) * $this->page_size, PDO::PARAM_INT );
  }

}
