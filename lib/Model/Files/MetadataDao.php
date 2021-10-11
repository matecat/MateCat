<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:34
 */

namespace Files;

use DataAccess_IDaoStruct;
use Database;

class MetadataDao extends \DataAccess_AbstractDao {
    const TABLE = 'file_metadata';

    /**
     * @param     $id_project
     * @param     $id_file
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getByJobIdProjectAndIdFile( $id_project, $id_file, $ttl = 0 ) {
        $stmt = $this->_getStatementForCache(
                "SELECT * FROM " . self::TABLE . " WHERE " .
                " id_project = :id_project " .
                " AND id_file = :id_file "
        );

        $result = $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new MetadataStruct(), [
                'id_project' => $id_project,
                'id_file'    => $id_file,
        ] );

        return @$result;
    }

    /**
     * @param     $id_project
     * @param     $id_file
     * @param     $key
     *
     * @param int $ttl
     *
     * @return MetadataStruct
     */
    public function get( $id_project, $id_file, $key, $ttl = 0 ) {

        $stmt = $this->_getStatementForCache(
                "SELECT * FROM " . self::TABLE . " WHERE " .
                " id_project = :id_project " .
                " AND id_file = :id_file " .
                " AND `key` = :key "
        );

        return @$this->setCacheTTL( $ttl )->_fetchObject( $stmt, new MetadataStruct(), [
                'id_project' => $id_project,
                'id_file'    => $id_file,
                'key'        => $key
        ] )[ 0 ];

    }

    public function insert( $id_project, $id_file, $key, $value ) {
        $sql = "INSERT INTO file_metadata " .
                " ( id_project, id_file, `key`, `value` ) " .
                " VALUES " .
                " ( :id_project, :id_file, :key, :value ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_project' => $id_project,
                'id_file'    => $id_file,
                'key'        => $key,
                'value'      => $value
        ] );

        return $this->get( $id_project, $id_file, $key );
    }


    public function update( $id_project, $id_file, $key, $value ) {
        $sql = "UPDATE file_metadata SET `value` = :value WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key  ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_project' => $id_project,
                'id_file'    => $id_file,
                'key'        => $key,
                'value'      => $value
        ] );

        return $this->get( $id_project, $id_file, $key );
    }
}