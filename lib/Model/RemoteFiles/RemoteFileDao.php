<?php

class RemoteFiles_RemoteFileDao extends DataAccess_AbstractDao {
    /**
     * @param int       $id_file
     * @param int       $id_job
     * @param string    $remote_id
     */
    public static function insert( $id_file, $id_job, $remote_id ) {
        $data = array();
        $data[ 'id_file' ] = (int) $id_file;
        $data[ 'id_job' ] = (int) $id_job;
        $data[ 'remote_id' ] = (string) $remote_id;

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
            " WHERE id_job = :id_job "
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
    public static function getByFileId( $id_file ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM remote_files " .
            " WHERE id_file = :id_file "
        );

        $stmt->execute( array( 'id_file' => $id_file ) );
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
          "   AND id_job = :id_job"
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
        $stmt = $conn->prepare("SELECT count(id) FROM remote_files WHERE id_job = :id_job");
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

