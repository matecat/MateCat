<?php


namespace Model\ConnectedServices;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\Exceptions\ValidationError;
use Model\Users\UserStruct;
use PDO;
use Utils\Tools\Utils;

class ConnectedServiceDao extends AbstractDao
{

    const string TABLE = 'connected_services';
    const string GDRIVE_SERVICE = 'gdrive';

    protected static array $primary_keys = ['id'];
    protected static array $auto_increment_field = ['id'];

    /**
     * @param $id
     *
     * @return ConnectedServiceStruct|false
     */
    public function findById($id): ConnectedServiceStruct|false
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE id = :id"
        );
        $stmt->setFetchMode(PDO::FETCH_CLASS, ConnectedServiceStruct::class);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    /**
     * @param string $token
     * @param ConnectedServiceStruct $service
     *
     * @return ConnectedServiceStruct
     * @throws Exception
     */
    public function updateOauthToken(string $token, ConnectedServiceStruct $service): ConnectedServiceStruct
    {
        $service->updated_at = Utils::mysqlTimestamp(time());
        $service->setEncryptedAccessToken($token);

        $this->updateStruct($service, ['fields' => ['oauth_access_token', 'updated_at']]);

        return $service;
    }

    /**
     * @param                        $time
     * @param ConnectedServiceStruct $service
     *
     * @return int
     * @throws Exception
     */
    public function setServiceExpired($time, ConnectedServiceStruct $service): int
    {
        $service->expired_at = Utils::mysqlTimestamp($time);

        return $this->updateStruct($service, ['fields' => ['expired_at']]);
    }

    /**
     * Sets the default ConnectedService
     * @throws ValidationError
     */
    public function setDefaultService(ConnectedServiceStruct $service): void
    {
        if (empty($service->uid) || empty($service->service)) {
            throw  new ValidationError('Service is not valid for update');
        }

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
            "UPDATE connected_services SET is_default = 0 WHERE uid = :uid AND service = :service"
        );
        $stmt->execute(['uid' => $service->uid, 'service' => $service->service]);

        $stmt = $conn->prepare(
            "UPDATE connected_services SET is_default = 1 WHERE uid = :uid AND service = :service AND id = :id"
        );
        $stmt->execute(['uid' => $service->uid, 'service' => $service->service, 'id' => $service->id]);
    }

    /**
     * @param UserStruct $user
     * @param                  $id_service
     *
     * @return ?ConnectedServiceStruct
     */
    public function findServiceByUserAndId(UserStruct $user, $id_service): ?ConnectedServiceStruct
    {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid AND id = :id "
        );

        $stmt->setFetchMode(PDO::FETCH_CLASS, ConnectedServiceStruct::class);
        $stmt->execute(
            ['uid' => $user->uid, 'id' => $id_service]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * @param UserStruct $user
     *
     * @return ConnectedServiceStruct[]
     */
    public function findServicesByUser(UserStruct $user): array
    {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid "
        );

        $stmt->setFetchMode(PDO::FETCH_CLASS, ConnectedServiceStruct::class);
        $stmt->execute(
            ['uid' => $user->uid]
        );

        return $stmt->fetchAll();
    }

    /**
     * @param UserStruct $user
     * @param string $name
     *
     * @return ConnectedServiceStruct|null
     */

    public function findDefaultServiceByUserAndName(UserStruct $user, string $name): ?ConnectedServiceStruct
    {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(
            "SELECT * FROM connected_services WHERE " .
            " uid = :uid AND service = :service AND is_default LIMIT 1"
        );

        $stmt->setFetchMode(PDO::FETCH_CLASS, ConnectedServiceStruct::class);
        $stmt->execute(
            ['uid' => $user->uid, 'service' => $name]
        );

        return $stmt->fetch() ?: null;
    }


    /**
     * @param UserStruct $user
     * @param string $service
     * @param string $email
     *
     * @return ?ConnectedServiceStruct
     */
    public function findUserServicesByNameAndEmail(UserStruct $user, string $service, string $email): ?ConnectedServiceStruct
    {
        $stmt = $this->database->getConnection()->prepare(
            " SELECT * FROM connected_services WHERE " .
            " uid = :uid AND service = :service AND email = :email "
        );

        $stmt->setFetchMode(PDO::FETCH_CLASS, ConnectedServiceStruct::class);
        $stmt->execute([
            'uid' => $user->uid,
            'service' => $service,
            'email' => $email
        ]);

        return $stmt->fetch() ?: null;
    }

    protected function _buildResult(array $array_result)
    {
    }
}