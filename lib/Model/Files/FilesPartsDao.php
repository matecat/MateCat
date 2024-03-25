<?php

namespace Files;

use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;

class FilesPartsDao extends DataAccess_AbstractDao {

    /**
     * @param FilesPartsStruct $filesPartsStruct
     *
     * @return int
     */
    public function insert( FilesPartsStruct $filesPartsStruct ) {
        $sql = "INSERT INTO files_parts " .
                " ( `id_file`, `tag_key`, `tag_value` ) " .
                " VALUES " .
                " ( :id_file, :key, :value ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_file' => $filesPartsStruct->id_file,
                'key'     => $filesPartsStruct->key,
                'value'   => $filesPartsStruct->value
        ] );

        if ( $stmt->rowCount() === 1 ) {
            return $conn->lastInsertId();
        }

        return 0;
    }

    /**
     * @param array $ids
     * @param int   $ttl
     *
     * @return \DataAccess_IDaoStruct[]
     */
    public function getFirstAndLastSegmentForArrayOfFilePartsIds( array $ids, $ttl = 86400 ) {
        $return  = [];
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT 
                min(s.id) as first_segment, 
                max(s.id) as last_segment, 
                s.id_file_part as id 
                FROM
                files_parts fp
                left join segments s on fp.id_file = s.id_file
                where fp.id IN ( " . implode( ', ', $ids ) . " )
                and s.id_file_part IN ( " . implode( ', ', $ids ) . " )
                group by s.id_file_part
            ";

        $stmt = $conn->prepare( $sql );

        $data = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [] );

        foreach ( $data as $datum ) {
            $return[ $datum[ 'id' ] ] = $datum;
        }

        return $return;
    }

    /**
     * **********************************
     * DO NOT USE THIS METHOD IN A LOOP FUNCTION
     * **********************************
     *
     * @param     $id
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getFirstAndLastSegment( $id, $ttl = 86400 ) {
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

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'id' => $id ] )[ 0 ];
    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getById( $id, $ttl = 0 ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT * FROM files_parts  WHERE id = :id ";
        $stmt    = $conn->prepare( $sql );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'id' => $id ] )[ 0 ];
    }

    /**
     * @param     $fileId
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct[]
     */
    public function getByFileId( $fileId, $ttl = 86400 ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT * FROM files_parts  WHERE id_file = :fileId ";
        $stmt    = $conn->prepare( $sql );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'fileId' => $fileId ] );
    }

    /**
     * @param     $segmentId
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getBySegmentId( $segmentId, $ttl = 86400 ) {
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

        return @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'segmentId' => $segmentId ] )[ 0 ];
    }
}