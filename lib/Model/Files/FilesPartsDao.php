<?php

namespace Files ;

use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;
use Files_FileStruct;
use PDO;

class FilesPartsDao extends  DataAccess_AbstractDao {

    /**
     * @param FilesPartsStruct $filesPartsStruct
     *
     * @return int
     */
    public function insert(FilesPartsStruct $filesPartsStruct) {
        $sql = "INSERT INTO files_parts " .
                " ( `id_file`, `tag_key`, `tag_value` ) " .
                " VALUES " .
                " ( :id_file, :key, :value ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(  $sql );
        $stmt->execute( array(
                'id_file' => $filesPartsStruct->id_file,
                'key' => $filesPartsStruct->key,
                'value' => $filesPartsStruct->value
        ) );

        if($stmt->rowCount() === 1){
            return $conn->lastInsertId();
        }

        return 0;
    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getById( $id, $ttl = 0) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT * FROM files_parts  WHERE id = :id ";
        $stmt    = $conn->prepare( $sql );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'id' => $id ] )[ 0 ];
    }
}