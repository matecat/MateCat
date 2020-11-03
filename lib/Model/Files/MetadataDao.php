<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:34
 */
namespace Files;

use Database;
use Files\MetadataStruct as Files_MetadataStruct;

class MetadataDao extends \DataAccess_AbstractDao {
    const TABLE = 'file_metadata' ;

    /**
     * @param $id_project
     * @param $id_file
     *
     * @return \DataAccess_IDaoStruct[]
     */
    public function getByJobIdProjectAndIdFile( $id_project, $id_file ) {
        $stmt = $this->_getStatementForCache(
                "SELECT * FROM ".self::TABLE." WHERE " .
                " id_project = :id_project " .
                " AND id_file = :id_file "
        );

        $result = $this->_fetchObject( $stmt, new Files_MetadataStruct(), array(
                'id_project' => $id_project,
                'id_file' => $id_file,
        ) );

        return @$result;
    }

    public function get($id_project, $id_file,  $key ) {
        $stmt = $this->_getStatementForCache(
                "SELECT * FROM ".self::TABLE." WHERE " .
                " id_project = :id_project " .
                " AND id_file = :id_file " .
                " AND `key` = :key "
        );

        $result = $this->_fetchObject( $stmt, new Files_MetadataStruct(), array(
                'id_project' => $id_project,
                'id_file' => $id_file,
                'key' => $key
        ) );

        return @$result[0];

    }

    public function insert($id_project, $id_file, $key, $value) {
        $sql = "INSERT INTO file_metadata " .
                " ( id_project, id_file, `key`, `value` ) " .
                " VALUES " .
                " ( :id_project, :id_file, :key, :value ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(  $sql );
        $stmt->execute( array(
                'id_project' => $id_project,
                'id_file' => $id_file,
                'key' => $key,
                'value' => $value
        ) );

        return $this->get($id_project, $id_file, $key);
    }


    public function update($id_project, $id_file, $key, $value) {
        $sql = "UPDATE file_metadata SET `value` = :value WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key  ";

                $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(  $sql );
        $stmt->execute( array(
                'id_project' => $id_project,
                'id_file' => $id_file,
                'key' => $key,
                'value' => $value
        ) );

        return $this->get($id_project, $id_file, $key);
    }
}