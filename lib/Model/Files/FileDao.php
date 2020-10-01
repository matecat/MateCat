<?php

class Files_FileDao extends DataAccess_AbstractDao {
    const TABLE = "files";

    protected static $auto_increment_field = ['id'] ;

    /**
     * @param     $id_job
     *
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|Files_FileStruct[]
     */
    public static function getByJobId( $id_job, $ttl = 60 ) {

        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT * FROM files " .
                " INNER JOIN files_job ON files_job.id_file = files.id " .
                " AND id_job = :id_job "
        );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Files_FileStruct, [ 'id_job' => $id_job ] );

    }

    /**
     * @param     $id_project
     *
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|Files_FileStruct[]
     */
    public static function getByProjectId( $id_project, $ttl = 600 ) {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id_project = :id_project ");
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Files_FileStruct, [ 'id_project' => $id_project ] );
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

    public static function isFileInProject($id_file, $id_project) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id_project = :id_project and id = :id_file ");
        $stmt->execute( [ 'id_project' => $id_project, 'id_file' => $id_file ] );
        return $stmt->rowCount();
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

    public function deleteFailedProjectFiles( $idFiles = [] ){

        if ( empty( $idFiles ) ) return 0;

        $sql = "DELETE FROM files WHERE id IN ( " . str_repeat( '?,', count( $idFiles ) - 1) . '?' . " ) ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $success = $stmt->execute( $idFiles );
        return $stmt->rowCount();

    }

    public static function insertFilesJob( $id_job, $id_file ) {

        $data              = [];
        $data[ 'id_job' ]  = (int)$id_job;
        $data[ 'id_file' ] = (int)$id_file;

        $db = Database::obtain();
        $db->insert( 'files_job', $data );

    }

}
