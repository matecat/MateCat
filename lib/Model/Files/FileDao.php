<?php

class Files_FileDao extends DataAccess_AbstractDao {

    public static function getByJobId( $id_job ) {
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(
        "SELECT * FROM files " .
        " INNER JOIN files_job ON files_job.id_file = files.id " .
        " AND id_job = :id_job "
      );

      $stmt->execute( array( 'id_job' => $id_job ) );
      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Files_FileStruct');
      return $stmt->fetchAll();
    }

    function getByProjectId( $id_project ) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id_project = ? ");
        $stmt->execute( array( $id_project ) );
        return $stmt->fetchAll();
    }

    function _buildResult( $array_result ) {
        return null;
    }

}
