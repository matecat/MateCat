<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 15:19
 */

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use DataAccess_AbstractDao;
use Database;
use PDO;

class DqfProjectMapDao extends DataAccess_AbstractDao  {

    const TABLE       = "dqf_projects_map";
    const STRUCT_TYPE = "\Features\Dqf\Model\DqfProjectMapStruct";

    const PROJECT_TYPE_TRANSLATE = 'translate' ;
    const PROJECT_TYPE_REVISE    = 'revise' ;

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];


    /**
     * Finds the parent record for translation.
     * first, search any non archived transaltion job, then search for vendor_root, then search for master type.
     *
     * @return \Features\Dqf\Model\DqfProjectMapStruct|null
     */
    public function findById( $id ) {
        $sql = "SELECT * FROM dqf_projects_map WHERE id = :id  " ;
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute([ 'id' => $id ]);
        return $stmt->fetch() ;
    }

    /**
     * Finds the parent record for translation.
     * first, search any non archived transaltion job, then search for vendor_root, then search for master type.
     *
     * @return \Features\Dqf\Model\DqfProjectMapStruct|null
     */
    public function findRootProject( Chunks_ChunkStruct $chunk) {
        $sql = "SELECT * FROM dqf_projects_map
                WHERE id_job = :id_job
                 AND archive_date IS NULL
                 AND project_type in ('master', 'vendor_root')
                 ORDER BY
                    project_type = 'vendor_root' DESC,
                    project_type = 'master' DESC
                " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'   => $chunk->id,
        ]);

        return $stmt->fetch() ;
    }


    public function getByTypeWithArchived( Chunks_ChunkStruct $chunk, $type ) {
        return $this->getByType( $chunk, $type, true );
    }
    /**
     * @param Chunks_ChunkStruct $chunk
     * @param                    $type
     * @param bool               $include_archived
     *
     * @return array
     *
     */
    public function getByType( Chunks_ChunkStruct $chunk, $type, $include_archived = false ) {
        $archived_condition = $include_archived ? "" : " AND archive_date IS NULL " ;

        $sql = "SELECT * FROM dqf_projects_map
                  WHERE id_job = :id_job
                      $archived_condition
                      AND dqf_parent_uuid IS NOT NULL
                      AND project_type = :project_type
                  ORDER BY id " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'       => $chunk->id,
                'project_type' => $type
        ]);
        return $stmt->fetchAll() ;
    }

    /**
     *
     * @param $chunk
     *
     * @return DqfProjectMapStruct[]
     */
    public function getByChunk( $chunk ) {
        $sql = "SELECT * FROM dqf_projects_map WHERE id_job = :id_job
                  AND password = :password  AND archive_date IS NULL
                  ORDER BY id " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'   => $chunk->id,
                'password' => $chunk->password,
        ]);

        return $stmt->fetchAll() ;
    }

    /**
     * @param $chunk
     *
     * @return \Features\Dqf\Model\DqfProjectMapStruct
     */
    public function getMasterByChunk( $chunk ) {
        $sql = "SELECT * FROM dqf_projects_map WHERE id_job = :id_job
                  AND password = :password AND dqf_parent_uuid IS NULL " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'   => $chunk->id,
                'password' => $chunk->password,
        ]);

        return $stmt->fetch() ;
    }

    public function getChildByChunk( $chunk ) {
        $sql = "SELECT * FROM dqf_projects_map WHERE id_job = :id_job
                  AND password = :password AND dqf_parent_uuid IS NOT NULL
                  AND archive_date IS NULL " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'   => $chunk->id,
                'password' => $chunk->password,
        ]);

        return $stmt->fetchAll() ;
    }

}