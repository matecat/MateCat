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
    const STRUCT_TYPE = "\\Teams\\MembershipStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    public function findById( $id ) {
        $sql = " SELECT * FROM " . self::STRUCT_TYPE . " WHERE id = ? " ;
        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute( array( $id ) );

        return $stmt->fetch() ;
    }

    /**
     *
     * @param \Users_UserStruct $user
     */
    public function findTeamsbyUser( \Users_UserStruct $user ) {
        $sql = " SELECT * FROM teams JOIN teams_users ON teams_users.id_team = teams.id " .
            " WHERE teams_users.uid = ? " ;

        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Teams\TeamStruct' );
        $stmt->execute( array( $user->uid ) ) ;

        return $stmt->fetchAll() ;
    }

    protected function _buildResult($array_result)
    {
        // TODO: Implement _buildResult() method.
    }
}