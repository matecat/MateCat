<?php


namespace ConnectedServices ;

use Database ;
use PDO ;

class ConnectedServiceDao extends \DataAccess_AbstractDao {

    /**
     * @param \Users_UserStruct $user
     * @param $name
     *
     * @return \ConnectedServices\ConnectedServiceStruct
     *
     */
    public function findServiceByUserAndName( \Users_UserStruct $user, $name ) {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid AND service = :service "
        );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
            array( 'uid' => $user->uid, 'service' => $name )
        );

        return $stmt->fetch();
    }

    protected function _buildResult($array_result)
    {
        // TODO: Implement _buildResult() method.
    }
}