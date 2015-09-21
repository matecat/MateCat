<?php

class Jobs_JobDao extends DataAccess_AbstractDao {

  public static function getById( $id ) {
    Log::doLog('Jobs_JobDao getById', $id);

    $conn = Database::obtain()->getConnection();
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute( array( $id ) ) ;
    $stmt->setFetchMode(PDO::FETCH_CLASS, 'Jobs_JobStruct');
    return $stmt->fetch();
  }

  protected function _buildResult( $array_result ) {

  }

}
