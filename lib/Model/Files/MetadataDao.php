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
}