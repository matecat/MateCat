<?php

class RemoteFiles_RemoteFileDao extends DataAccess_AbstractDao {
    /**
     * @param int    $id_file
     * @param int    $id_job
     * @param string $remote_id
     * @param        $connected_service_id
     * @param int    $is_original
     *
     * @throws Exception
     */
    public static function insert( $id_file, $id_job, $remote_id, $connected_service_id, $is_original = 0 ) {
        $data = array();
        $data[ 'id_file' ] = (int) $id_file;
        $data[ 'id_job' ] = (int) $id_job;
        $data[ 'remote_id' ] = (string) $remote_id;
        $data[ 'is_original' ] = $is_original;

        $data[ 'connected_service_id'] = $connected_service_id ;

        $db = Database::obtain();
        $db->insert( 'remote_files', $data );
    }

    /**
     * @param $id_job
     *
     * @return RemoteFiles_RemoteFileStruct[]
     */
    public static function getByJobId( $id_job ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM remote_files " .
            " WHERE id_job = :id_job " .
            "   AND is_original = 0 "
        );

        $stmt->execute( [ 'id_job' => $id_job ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, RemoteFiles_RemoteFileStruct::class );

        return $stmt->fetchAll();
    }


    /**
     * @param $id_job
     *
     * @return RemoteFiles_RemoteFileStruct[]
     */
    public static function getOriginalsByJobId( $id_job ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT r.* FROM remote_files r " .
            " INNER JOIN files_job fj " .
            "    ON r.id_file = fj.id_file " .
            " WHERE fj.id_job = :id_job " .
            "   AND r.is_original = 1 " .
            " ORDER BY r.id_file "
        );

        $stmt->execute( array( 'id_job' => $id_job ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'RemoteFiles_RemoteFileStruct');
        return $stmt->fetchAll();
    }

    /**
     * @param $id_file
     *
     * @return RemoteFiles_RemoteFileStruct[]
     */
    public static function getByFileId( $id_file, $is_original = 0 ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM remote_files " .
            " WHERE id_file = :id_file " .
            "   AND is_original = :is_original "
        );

        $stmt->execute( array( 'id_file' => $id_file, 'is_original' => $is_original ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'RemoteFiles_RemoteFileStruct');
        return $stmt->fetchAll();
    }

    /**
     * @param $id_file
     * @param $id_job
     *
     * @return RemoteFiles_RemoteFileStruct
     */
    public static function getByFileAndJob( $id_file, $id_job ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
          "SELECT * FROM remote_files " .
          " WHERE id_file = :id_file " .
          "   AND id_job = :id_job" .
          "   AND is_original = 0 "
        );

        $stmt->execute( array( 'id_file' => $id_file, 'id_job' => $id_job ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'RemoteFiles_RemoteFileStruct');
        return $stmt->fetch();
    }

    /**
     * @param int $id_job
     * 
     * @return boolean
     */
    public static function jobHasRemoteFiles( $id_job ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "  SELECT count(id) "
            . "  FROM remote_files "
            . " WHERE id_job = :id_job "
            . "   AND is_original = 0 "
        );
        $stmt->setFetchMode( PDO::FETCH_NUM );
        $stmt->execute( array( 'id_job' => $id_job ) );

        $result = $stmt->fetch();

        $countRemoteFiles = $result[ 0 ];

        if( $countRemoteFiles > 0 ) {
            return true;
        }

        return false;
    }

    protected function _buildResult($array_result) {
        return null;
    }
}

