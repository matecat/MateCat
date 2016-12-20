<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Teams;

use PDO;

class TeamDao extends \DataAccess_AbstractDao {

    const TABLE = "teams";
    const STRUCT_TYPE = "TeamStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    public function findById( $id ) {
        $sql = " SELECT * FROM teams WHERE id = ? " ;
        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'TeamStruct' );
        $stmt->execute($id);

        return $stmt->fetch() ;
    }

    protected function _buildResult($array_result)
    {
        // TODO: Implement _buildResult() method.
    }

}