<?php


namespace ConnectedServices;

use DataAccess\AbstractDao;
use Exception;
use Exceptions\ValidationError;
use PDO;
use Users_UserStruct;
use Utils;

class ConnectedServiceDao extends AbstractDao {

    const TABLE          = 'connected_services';
    const GDRIVE_SERVICE = 'gdrive';

    protected static array $primary_keys         = [ 'id' ];
    protected static array $auto_increment_field = [ 'id' ];

    /**
     * @param $id
     *
     * @return ConnectedServiceStruct
     */
    public function findById( $id ) {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
                "SELECT * FROM connected_services WHERE id = :id"
        );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute( [ 'id' => $id ] );

        return $stmt->fetch();
    }

    /**
     * @param                        $token
     * @param ConnectedServiceStruct $service
     *
     * @return ConnectedServiceStruct
     * @throws Exception
     */
    public function updateOauthToken( $token, ConnectedServiceStruct $service ): ConnectedServiceStruct {
        $service->updated_at = Utils::mysqlTimestamp( time() );
        $service->setEncryptedAccessToken( $token );

        $this->updateStruct( $service, [ 'fields' => [ 'oauth_access_token', 'updated_at' ] ] );

        return $service;
    }

    /**
     * @param                        $time
     * @param ConnectedServiceStruct $service
     *
     * @return int
     * @throws Exception
     */
    public function setServiceExpired( $time, ConnectedServiceStruct $service ): int {
        $service->expired_at = Utils::mysqlTimestamp( $time );

        return $this->updateStruct( $service, [ 'fields' => [ 'expired_at' ] ] );
    }

    /**
     * Sets the default ConnectedService
     */
    public function setDefaultService( ConnectedServiceStruct $service ) {
        if ( empty( $service->uid ) || empty( $service->service ) ) {
            throw  new ValidationError( 'Service is not valid for update' );
        }

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
                "UPDATE connected_services SET is_default = 0 WHERE uid = :uid AND service = :service"
        );
        $stmt->execute( [ 'uid' => $service->uid, 'service' => $service->service ] );

        $stmt = $conn->prepare(
                "UPDATE connected_services SET is_default = 1 WHERE uid = :uid AND service = :service AND id = :id"
        );
        $stmt->execute( [ 'uid' => $service->uid, 'service' => $service->service, 'id' => $service->id ] );
    }

    /**
     * @param Users_UserStruct $user
     * @param                  $id_service
     *
     * @return ?ConnectedServiceStruct
     */
    public function findServiceByUserAndId( Users_UserStruct $user, $id_service ): ?ConnectedServiceStruct {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
                "SELECT * FROM connected_services WHERE " .
                " uid = :uid AND id = :id "
        );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
                [ 'uid' => $user->uid, 'id' => $id_service ]
        );

        return $stmt->fetch();

    }

    /**
     * @param Users_UserStruct $user
     *
     * @return ConnectedServiceStruct[]
     */
    public function findServicesByUser( Users_UserStruct $user ): array {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
                "SELECT * FROM connected_services WHERE " .
                " uid = :uid "
        );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
                [ 'uid' => $user->uid ]
        );

        return $stmt->fetchAll();
    }

    /**
     * @param Users_UserStruct $user
     * @param                  $name
     *
     * @return ConnectedServiceStruct[]
     *
     */
    public function findServicesByUserAndName( Users_UserStruct $user, $name ) {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
                "SELECT * FROM connected_services WHERE " .
                " uid = :uid AND service = :service "
        );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
                [ 'uid' => $user->uid, 'service' => $name ]
        );

        return $stmt->fetchAll();
    }

    /**
     * @param Users_UserStruct $user
     * @param                  $name
     *
     * @return ConnectedServiceStruct|null
     */

    public function findDefaultServiceByUserAndName( Users_UserStruct $user, $name ): ?ConnectedServiceStruct {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
                "SELECT * FROM connected_services WHERE " .
                " uid = :uid AND service = :service AND is_default LIMIT 1"
        );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute(
                [ 'uid' => $user->uid, 'service' => $name ]
        );

        $result = $stmt->fetch();
        if ( empty( $result ) ) {
            return null;
        }

        /** @var $result ConnectedServiceStruct */
        return $result;

    }


    /**
     * @param Users_UserStruct $user
     * @param                  $service
     * @param                  $email
     *
     * @return ?ConnectedServiceStruct
     */
    public function findUserServicesByNameAndEmail( Users_UserStruct $user, $service, $email ): ?ConnectedServiceStruct {
        $stmt = $this->database->getConnection()->prepare(
                " SELECT * FROM connected_services WHERE " .
                " uid = :uid AND service = :service AND email = :email "
        );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute( [
                'uid'     => $user->uid,
                'service' => $service,
                'email'   => $email
        ] );

        return $stmt->fetch();
    }

    public function findByRemoteIdAndCode( $remote_id, $service ) {
        $stmt = $this->database->getConnection()->prepare(
                " SELECT * FROM connected_services WHERE " .
                " uid = :remote_id AND service = :service "
        );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ConnectedServices\ConnectedServiceStruct' );
        $stmt->execute( [
                'service'   => $service,
                'remote_id' => $remote_id
        ] );

        return $stmt->fetch();
    }

    protected function _buildResult( array $array_result ) {
        // TODO: Implement _buildResult() method.
    }
}