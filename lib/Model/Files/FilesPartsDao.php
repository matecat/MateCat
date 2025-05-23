<?php

namespace Files;

use DataAccess\AbstractDao;
use DataAccess\ShapelessConcreteStruct;
use Database;
use ReflectionException;

class FilesPartsDao extends AbstractDao {

    /**
     * @param FilesPartsStruct $filesPartsStruct
     *
     * @return int
     */
    public function insert( FilesPartsStruct $filesPartsStruct ): int {
        $sql = "INSERT INTO files_parts " .
                " ( `id_file`, `tag_key`, `tag_value` ) " .
                " VALUES " .
                " ( :id_file, :tag_key, :tag_value ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_file'   => $filesPartsStruct->id_file,
                'tag_key'   => $filesPartsStruct->tag_key,
                'tag_value' => $filesPartsStruct->tag_value
        ] );

        if ( $stmt->rowCount() === 1 ) {
            return $conn->lastInsertId();
        }

        return 0;
    }

    /**
     * **********************************
     * DO NOT USE THIS METHOD IN A LOOP FUNCTION
     * **********************************
     *
     * @param int $id
     * @param int $ttl
     *
     * @return ShapelessConcreteStruct|null
     * @throws ReflectionException
     */
    public function getFirstAndLastSegment( int $id, int $ttl = 86400 ): ?ShapelessConcreteStruct {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT 
                min(s.id) as first_segment, 
                max(s.id) as last_segment, 
                s.id_file_part as id 
                FROM
                files_parts fp
                left join segments s on fp.id_file = s.id_file
                where fp.id = :id
                and s.id_file_part = :id
            ";

        $stmt = $conn->prepare( $sql );

        /** @var  ShapelessConcreteStruct $result */
        $result = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [ 'id' => $id ] )[ 0 ] ?? null;

        return $result;

    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return FilesPartsStruct
     * @throws ReflectionException
     */
    public function getById( int $id, int $ttl = 0 ): ?FilesPartsStruct {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT * FROM files_parts  WHERE id = :id ";
        $stmt    = $conn->prepare( $sql );

        /** @var  FilesPartsStruct $result */
        $result = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, FilesPartsStruct::class, [ 'id' => $id ] )[ 0 ] ?? null;
        return $result;
    }

    /**
     * @param int $fileId
     * @param int $ttl
     *
     * @return FilesPartsStruct[]
     * @throws ReflectionException
     */
    public function getByFileId( int $fileId, int $ttl = 86400 ): array {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT * FROM files_parts  WHERE id_file = :fileId ";
        $stmt    = $conn->prepare( $sql );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, FilesPartsStruct::class, [ 'fileId' => $fileId ] );
    }

    /**
     * @param int $segmentId
     * @param int $ttl
     *
     * @return FilesPartsStruct
     * @throws ReflectionException
     */
    public function getBySegmentId( int $segmentId, int $ttl = 86400 ): ?FilesPartsStruct {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "
            SELECT 
                fp.id,
                fp.id_file,
                fp.tag_key,
                fp.tag_value 
            FROM segments s
                LEFT JOIN files_parts fp ON fp.id = s.id_file_part
             WHERE s.id = :segmentId; ";

        $stmt = $conn->prepare( $sql );

        /** @var  FilesPartsStruct $result */
        $result = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, FilesPartsStruct::class, [ 'segmentId' => $segmentId ] )[ 0 ] ?? null;

        return $result;
    }
}