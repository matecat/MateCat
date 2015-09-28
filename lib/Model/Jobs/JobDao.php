<?php

class Jobs_JobDao extends DataAccess_AbstractDao {

  public static function getByProjectId( $id_project ) {
    $conn = Database::obtain()->getConnection();
    $stmt = $conn->prepare(
      "SELECT id, password FROM ( " .
      " SELECT * FROM jobs " .
      " WHERE id_project = ? " .
      " ORDER BY id DESC ) t GROUP BY id ; ") ;

    $stmt->setFetchMode(PDO::FETCH_CLASS, 'Jobs_JobStruct');
    $stmt->execute( array( $id_project ) );

    return $stmt->fetchAll();
  }

  protected function _buildResult( $array_result ) {

  }

}
