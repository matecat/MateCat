<?php
namespace Files;

use DataAccess\AbstractDao;
use Database;
use Exception;
use PDO;
use ReflectionException;

class FileDao extends AbstractDao {
    const TABLE = "files";

    protected static array $auto_increment_field = [ 'id' ];

    /**
     * @param     $id_job
     *
     * @param int $ttl
     *
     * @return FileStruct[]
     * @throws ReflectionException
     */
    public static function getByJobId( $id_job, int $ttl = 60 ): array {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare(
                "SELECT * FROM files " .
                " INNER JOIN files_job ON files_job.id_file = files.id " .
                " AND id_job = :id_job "
        );

        /** @var FileStruct[] */
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new FileStruct, [ 'id_job' => $id_job ] );

    }

    /**
     * @param int $id_project
     *
     * @param int $ttl
     *
     * @return FileStruct[]
     * @throws ReflectionException
     */
    public static function getByProjectId( int $id_project, int $ttl = 600 ): array {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( "SELECT * FROM files where id_project = :id_project " );

        /** @var FileStruct[] */
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new FileStruct, [ 'id_project' => $id_project ] );
    }

    public static function updateField( $file, $field, $value ): bool {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "UPDATE files SET $field = :value " .
                " WHERE id = :id "
        );

        return $stmt->execute( [
                'value' => $value,
                'id'    => $file->id
        ] );
    }

    /**
     * @param int $id_file
     * @param int $id_project
     *
     * @return int
     */
    public static function isFileInProject( int $id_file, int $id_project ): int {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id_project = :id_project and id = :id_file " );
        $stmt->execute( [ 'id_project' => $id_project, 'id_file' => $id_file ] );

        return $stmt->rowCount();
    }

    /**
     * @param $id
     *
     * @return FileStruct
     */
    public static function getById( $id ): FileStruct {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM files where id = :id " );
        $stmt->execute( [ 'id' => $id ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, FileStruct::class );

        return $stmt->fetch();
    }

    /**
     * @param array $idFiles
     *
     * @return int
     */
    public function deleteFailedProjectFiles( array $idFiles = [] ): int {

        if ( empty( $idFiles ) ) {
            return 0;
        }

        $sql  = "DELETE FROM files WHERE id IN ( " . str_repeat( '?,', count( $idFiles ) - 1 ) . '?' . " ) ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $idFiles );

        return $stmt->rowCount();

    }

    /**
     * @throws Exception
     */
    public static function insertFilesJob( $id_job, $id_file ) {

        $data              = [];
        $data[ 'id_job' ]  = (int)$id_job;
        $data[ 'id_file' ] = (int)$id_file;

        $db = Database::obtain();
        $db->insert( 'files_job', $data );

    }

}
