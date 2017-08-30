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
use Features\Dqf\Service\Struct\Request\ChildProjectRequestStruct;
use Files_FileStruct;
use PDO;

class DqfProjectMapDao extends DataAccess_AbstractDao  {

    const TABLE       = "dqf_child_projects_map";
    const STRUCT_TYPE = "\Features\Dqf\Model\DqfProjectMapStruct";

    protected static $auto_increment_fields = [ 'id' ];
    protected static $primary_keys          = [ 'id' ];

    /**
     *
     * @param $chunk
     *
     * @return DqfProjectMapStruct[]
     */
    public function getByChunk( $chunk ) {
        $sql = "SELECT * FROM dqf_child_projects_map WHERE id_job = :id_job
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

    public function getMasterByChunk( $chunk ) {
        $sql = "SELECT * FROM dqf_child_projects_map WHERE id_job = :id_job
                  AND password = :password AND dqf_parent_uuid IS NULL " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'   => $chunk->id,
                'password' => $chunk->password,
        ]);

        return $stmt->fetchAll() ;
    }

    public function getChildByChunk( $chunk ) {
        $sql = "SELECT * FROM dqf_child_projects_map WHERE id_job = :id_job
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




    /**
     *
     * Return one record where id job and start and stop segment match, ignore archived records.
     *
     * @param Chunks_ChunkStruct $chunk
     *
     * @return DqfProjectMapStruct[]
     */
    public function getByChunkAndFile( Chunks_ChunkStruct $chunk, Files_FileStruct $file ){
        list ( $min, $max ) = $file->getMaxMinSegmentBoundariesForChunk( $chunk );
        return $this->getByChunkAndSegmentsInterval( $chunk, $min, $max );
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     * @param                    $min
     * @param                    $max
     *
     * @return DqfProjectMapStruct[]
     */
    public function getByChunkAndSegmentsInterval( Chunks_ChunkStruct $chunk, $min, $max) {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id_job = :id_job AND  " .
                " password = :password AND archive_date IS NULL AND " .
                " (
                ( first_segment BETWEEN :min AND :max ) OR ( last_segment BETWEEN :min AND :max )
            ) " ;

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        $stmt->execute([
                'id_job'   => $chunk->id,
                'password' => $chunk->password,
                'min'      => $min,
                'max'      => $max
        ]);

        return $stmt->fetchAll() ;
    }

}