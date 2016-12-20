<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Teams;

use PDO ;

class MembershipDao extends \DataAccess_AbstractDao
{

    const TABLE = "teams_users";
    const STRUCT_TYPE = "MembershipStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    public function findById( $id ) {
        $sql = " SELECT * FROM " . self::STRUCT_TYPE . " WHERE id = ? " ;
        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute($id);

        return $stmt->fetch() ;
    }

    protected function _buildResult($array_result)
    {
        // TODO: Implement _buildResult() method.
    }
}