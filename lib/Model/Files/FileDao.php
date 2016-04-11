<?php

class Files_FileDao extends DataAccess_AbstractDao {

    /**
     * @param $id_job
     *
     * @return Files_FileStruct[]
     */
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

    /**
     * @param $id_project
     *
     * @return Files_FileStruct[]
     */
    public static function getByProjectId( $id_project ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id_project = ? ");
        $stmt->execute( array( $id_project ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Files_FileStruct');
        return $stmt->fetchAll();
    }

    public static function getByRemoteId( $remote_id ) {
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(
        "  SELECT f.* "
        . "  FROM files f "
        . " INNER JOIN remote_files r "
        . "    ON f.id = r.id_file "
        . " WHERE r.remote_id = :remote_id "
        . "   AND r.is_original = 1 "
        . " ORDER BY f.id DESC "
        . " LIMIT 1 "
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

    /**
     * @param $id
     *
     * @return Files_FileStruct
     */
    public static function getById( $id ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id = :id ");
        $stmt->execute( array( 'id' => $id ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Files_FileStruct');
        return $stmt->fetch();
    }

    function _buildResult( $array_result ) {
        return null;
    }

}
