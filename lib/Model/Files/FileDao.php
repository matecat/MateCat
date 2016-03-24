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

    public static function getByRemoteId( $remote_id ) {
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(
        "SELECT * FROM files " .
        " WHERE remote_id = :remote_id "
      );

      $stmt->execute( array( 'remote_id' => $remote_id ) );
      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Files_FileStruct');
      return $stmt->fetch();
    }

    public static function updateField( $file, $field, $value ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "UPDATE files SET $field = :value " .
            " WHERE id = :id "
        );

        return $stmt->execute( array(
            'value' => $value,
            'id' => $file->id
        ));
    }

    function _buildResult( $array_result ) {
        return null;
    }

}
