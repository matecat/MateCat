<?php


namespace Model\ConnectedServices;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\InvalidatesUserProfileCache;
use Model\Exceptions\ValidationError;
use Model\Users\UserStruct;
use PDO;
use PDOException;
use TypeError;
use Utils\Tools\Utils;

class ConnectedServiceDao extends AbstractDao
{

    use InvalidatesUserProfileCache;

    const string TABLE = 'connected_services';
    const string GDRIVE_SERVICE = 'gdrive';

    /** @var list<string> */
    protected static array $primary_keys = ['id'];
    /** @var list<string> */
    protected static array $auto_increment_field = ['id'];

    /**
     * Both generic write paths drop the owner's cached profile.
     *
     * Hooking the three named write methods below was not enough: `ConnectedServicesController::update()`
     * disables a service by calling `updateStruct()` directly, and a service is created through
     * `insertStruct()` from outside this class, so both changed the profile payload without dropping it.
     * Overriding here makes the guarantee hold for every write through this DAO rather than for the
     * subset someone remembered to enumerate — which is the same argument that moved these hooks off
     * the controllers in the first place.
     *
     * @param array{fields?: list<string>} $options
     *
     * @throws Exception
     */
    public function updateStruct(IDaoStruct $struct, array $options = []): int
    {
        $updated = parent::updateStruct($struct, $options);

        $this->invalidateProfileOwning($struct);

        return $updated;
    }

    /**
     * @param array{ignore?: bool, no_nulls?: bool, on_duplicate_update?: array<string, string>}|null $options
     *
     * @throws Exception
     */
    public function insertStruct(IDaoStruct $struct, ?array $options = []): int|false
    {
        $inserted = parent::insertStruct($struct, $options);

        if ($inserted !== false) {
            $this->invalidateProfileOwning($struct);
        }

        return $inserted;
    }

    /**
     * `uid` is declared `public int` with no default, so a struct assembled field by field can reach a
     * partial-field write without it. isset() rather than a throw: the column is NOT NULL, so a write
     * that genuinely carried no uid failed before it got here, and an update of one column on a struct
     * loaded from the database always has it.
     *
     * @throws Exception
     */
    private function invalidateProfileOwning(IDaoStruct $struct): void
    {
        if ($struct instanceof ConnectedServiceStruct && isset($struct->uid)) {
            $this->invalidateUserProfileCache($struct->uid);
        }
    }

    /**
     * @param string $token
     * @param ConnectedServiceStruct $service
     *
     * @return ConnectedServiceStruct
     * @throws Exception
     * @throws TypeError
     */
    public function updateOauthToken(string $token, ConnectedServiceStruct $service): ConnectedServiceStruct
    {
        $service->updated_at = Utils::mysqlTimestamp(time());
        $service->setEncryptedAccessToken($token);

        // updateStruct() drops the cached profile.
        $this->updateStruct($service, ['fields' => ['oauth_access_token', 'updated_at']]);

        return $service;
    }

    /**
     * @param int $time
     * @param ConnectedServiceStruct $service
     *
     * @return int
     * @throws Exception
     */
    public function setServiceExpired(int $time, ConnectedServiceStruct $service): int
    {
        $service->expired_at = Utils::mysqlTimestamp($time);

        // updateStruct() drops the cached profile.
        $updated = $this->updateStruct($service, ['fields' => ['expired_at']]);

        return $updated;
    }

    /**
     * Sets the default ConnectedService
     * @throws ValidationError
     * @throws PDOException
     * @throws Exception
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

        // The guard above already rejected an empty uid.
        $this->invalidateUserProfileCache($service->uid);
    }

    /**
     * @param UserStruct $user
     * @param int $id_service
     *
     * @return ?ConnectedServiceStruct
     * @throws PDOException
     */
    public function findServiceByUserAndId(UserStruct $user, int $id_service): ?ConnectedServiceStruct
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
     * @throws PDOException
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
     * @throws PDOException
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
     * @throws PDOException
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

}
