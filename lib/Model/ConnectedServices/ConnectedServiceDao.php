<?php


namespace ConnectedServices ;

use Database ;
use Exceptions\ValidationError;
use PDO ;
use Utils ;

use ConnectedServices\ConnectedServiceStruct as Struct ;

class ConnectedServiceDao extends \DataAccess_AbstractDao {

    const TABLE = 'connected_services' ;
    const GDRIVE_SERVICE = 'gdrive' ;

    protected static $primary_keys = array('id');
    protected static $auto_increment_fields = array('id');

    public function findById( $id ) {
        $conn = $this->con->getConnection() ;
        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE id = :id"
        );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute( array( 'id' => $id ) );

        return $stmt->fetch();
    }

    /**
     * @param $token
     * @param ConnectedServiceStruct $service
     * @return ConnectedServiceStruct
     */
    public function updateOauthToken( $token, ConnectedServiceStruct $service ) {
        $service->updated_at = Utils::mysqlTimestamp( time() );
        $service->setEncryptedAccessToken( $token ) ;

        $this->updateStruct( $service, array('fields' => array('oauth_access_token', 'updated_at'))) ;
        return $service ;
    }

    public function setServiceExpired( $time, ConnectedServiceStruct $service ) {
        $service->expired_at = Utils::mysqlTimestamp( $time );
        return $this->updateStruct( $service, array('fields' => array('expired_at')));
    }

    /**
     * Sets the default ConnectedService
     */
    public function setDefaultService( ConnectedServiceStruct $service ) {
        if ( empty( $service->uid) || empty( $service->service ) ) {
            throw  new ValidationError('Service is not valid for update') ;
        }

        $conn = $this->con->getConnection() ;

        $stmt = $conn->prepare(
            "UPDATE connected_services SET is_default = 0 WHERE uid = :uid AND service = :service"
        );
        $stmt->execute( array( 'uid' => $service->uid, 'service' => $service->service ) );

        $stmt = $conn->prepare(
            "UPDATE connected_services SET is_default = 1 WHERE uid = :uid AND service = :service AND id = :id"
        );
        $stmt->execute( array( 'uid' => $service->uid, 'service' => $service->service, 'id' => $service->id ));
    }

    public function findServiceByUserAndId( \Users_UserStruct $user, $id_service ) {
        $conn = $this->con->getConnection() ;

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid AND id = :id "
        );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
            array( 'uid' => $user->uid, 'id' => $id_service )
        );

        return $stmt->fetch();

    }

    /**
     * @param \Users_UserStruct $user
     * @return ConnectedServiceStruct[]
     */
    public function findServicesByUser(\Users_UserStruct $user ) {
        $conn = $this->con->getConnection() ;

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid "
        );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
            array( 'uid' => $user->uid )
        );

        return $stmt->fetchAll();
    }

    /**
     * @param \Users_UserStruct $user
     * @param $name
     *
     * @return \ConnectedServices\ConnectedServiceStruct[]
     *
     */
    public function findServicesByUserAndName( \Users_UserStruct $user, $name ) {
        $conn = $this->con->getConnection() ;

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid AND service = :service "
        );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
            array( 'uid' => $user->uid, 'service' => $name )
        );

        return $stmt->fetchAll();
    }

    /**
     * @param \Users_UserStruct $user
     * @param $name
     * @return ConnectedServiceStruct
     */

    public function findDefaultServiceByUserAndName( \Users_UserStruct $user, $name ) {
        $conn = $this->con->getConnection() ;

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid AND service = :service AND is_default LIMIT 1"
        );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
            array( 'uid' => $user->uid, 'service' => $name )
        );

        return $stmt->fetch();
    }


    /**
     * @param \Users_UserStruct $user
     * @param $service
     * @param $email
     * @return mixed
     */
    public function findUserServicesByNameAndEmail( \Users_UserStruct $user, $service, $email ) {
        $stmt = $this->con->getConnection()->prepare(
            " SELECT * FROM connected_services WHERE " .
            " uid = :uid AND service = :service AND email = :email "
        );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute( array(
            'uid' => $user->uid,
            'service' => $service,
            'email' => $email
        ));

        return $stmt->fetch();
    }

    protected function _buildResult($array_result)
    {
        // TODO: Implement _buildResult() method.
    }
}