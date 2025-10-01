<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:34
 */

namespace Model\Files;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use ReflectionException;

class MetadataDao extends AbstractDao {

    const TABLE                              = 'file_metadata';
    const _query_metadata_by_project_id_file = "SELECT * FROM " . self::TABLE . " WHERE id_project = :id_project AND id_file = :id_file ";

    /**
     * @param int $id_project
     * @param int $id_file
     * @param int $ttl
     *
     * @return MetadataStruct[]
     * @throws ReflectionException
     */
    public function getByJobIdProjectAndIdFile( int $id_project, int $id_file, int $ttl = 0 ): ?array {
        $stmt = $this->_getStatementForQuery( self::_query_metadata_by_project_id_file );

        return $this->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, MetadataStruct::class, [
                'id_project' => $id_project,
                'id_file'    => $id_file,
        ] );
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByJobIdProjectAndIdFile( int $id_project, int $id_file ): bool {
        $stmt = $this->_getStatementForQuery( self::_query_metadata_by_project_id_file );

        return $this->_destroyObjectCache( $stmt, MetadataStruct::class, [ 'id_project' => $id_project, 'id_file' => $id_file, ] );
    }

    /**
     * @param int      $id_project
     * @param int      $id_file
     * @param string   $key
     * @param int|null $filePartsId
     * @param int      $ttl
     *
     * @return MetadataStruct
     * @throws ReflectionException
     */
    public function get( int $id_project, int $id_file, string $key, ?int $filePartsId = null, int $ttl = 0 ): ?MetadataStruct {

        $query = "SELECT * FROM " . self::TABLE . " WHERE " .
                " id_project = :id_project " .
                " AND id_file = :id_file " .
                " AND `key` = :key ";

        $params = [
                'id_project' => $id_project,
                'id_file'    => $id_file,
                'key'        => $key
        ];

        if ( $filePartsId ) {
            $query                      .= " AND `files_parts_id` = :files_parts_id";
            $params[ 'files_parts_id' ] = $filePartsId;
        }

        $stmt = $this->_getStatementForQuery( $query );

        return $this->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, MetadataStruct::class, $params )[ 0 ] ?? null;

    }

    /**
     * @param int      $id_project
     * @param int      $id_file
     * @param string   $key
     * @param string   $value
     * @param int|null $filePartsId
     *
     * @return MetadataStruct
     * @throws ReflectionException
     */
    public function insert( int $id_project, int $id_file, string $key, string $value, ?int $filePartsId = null ): ?MetadataStruct {

        $sql = "INSERT INTO file_metadata " .
                " ( id_project, id_file, `key`, `value`, `files_parts_id` ) " .
                " VALUES " .
                " ( :id_project, :id_file, :key, :value, :files_parts_id ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_project'     => $id_project,
                'id_file'        => $id_file,
                'files_parts_id' => $filePartsId,
                'key'            => $key,
                'value'          => $value
        ] );

        return $this->get( $id_project, $id_file, $key, $filePartsId );
    }

    /**
     * @param int      $id_project
     * @param int      $id_file
     * @param string   $key
     * @param string   $value
     * @param int|null $filePartsId
     *
     * @return MetadataStruct
     * @throws ReflectionException
     */
    public function update( int $id_project, int $id_file, string $key, string $value, ?int $filePartsId = null ): ?MetadataStruct {

        $sql = "UPDATE file_metadata SET `value` = :value WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key  ";

        $args = [
                'id_project' => $id_project,
                'id_file'    => $id_file,
                'key'        => $key,
                'value'      => $value
        ];

        if ( !empty( $filePartsId ) ) {
            $sql                      .= "AND `files_parts_id` = :files_parts_id";
            $args[ 'files_parts_id' ] = $filePartsId;
        }

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );


        $stmt->execute( $args );

        return $this->get( $id_project, $id_file, $key, $filePartsId );
    }

    /**
     * @param int      $id_project
     * @param int      $id_file
     * @param array    $metadata
     * @param int|null $filePartsId
     *
     * @return bool|void
     */
    public function bulkInsert( int $id_project, int $id_file, array $metadata = [], ?int $filePartsId = null ) {

        $sql         = "INSERT INTO file_metadata ( id_project, id_file, `key`, `value`, `files_parts_id` ) VALUES ";
        $bind_values = [];

        $index = 1;
        foreach ( $metadata as $key => $value ) {

            $isLast = ( $index === count( $metadata ) );

            if ( $value !== null and $value !== '' ) {
                $sql .= "(?,?,?,?,?)";

                if ( !$isLast ) {
                    $sql .= ',';
                }

                $bind_values[] = $id_project;
                $bind_values[] = $id_file;
                $bind_values[] = $key;
                $bind_values[] = $value;
                $bind_values[] = $filePartsId;
            }
            $index++;
        }

        if ( !empty( $bind_values ) ) {
            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare( $sql );

            return $stmt->execute( $bind_values );
        }
    }
}