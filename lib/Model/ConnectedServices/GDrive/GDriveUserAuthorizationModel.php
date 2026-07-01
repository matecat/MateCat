<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/11/2016
 * Time: 11:54
 */

namespace Model\ConnectedServices\GDrive;

use Exception;
use Google\Service\Oauth2\Userinfo;
use Google_Client;
use Google_Service_Oauth2;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\Exceptions\ValidationError;
use Model\Users\UserStruct;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

class GDriveUserAuthorizationModel
{

    protected UserStruct $user;

    protected Userinfo $userInfo;
    protected string $token;

    protected string $user_email;
    protected string $user_remote_id;
    protected string $user_name;

    protected ConnectedServiceDao $dao;
    protected ?Google_Client $googleClient = null;

    public function __construct(UserStruct $user, ConnectedServiceDao $dao, ?Google_Client $googleClient = null)
    {
        $this->user = $user;
        $this->dao = $dao;
        $this->googleClient = $googleClient;
    }

    /**
     * Updates or creates the service record.
     *
     * If the record does not exist, it is created.
     * If the record exists, it is updated.
     *
     * In the process, the current service becomes the default.
     * `is_default` flag from the other ones is removed.
     *
     * @param string $code
     *
     * @throws ValidationError
     * @throws \Google\Service\Exception
     * @throws Exception
     * @throws TypeError
     */
    public function updateOrCreateRecordByCode(string $code): void
    {
        $this->__collectProperties($code);

        // We have the user info email and name, we can save it along with the gdrive token to identify it.
        $service = $this->dao->findUserServicesByNameAndEmail(
            $this->user,
            ConnectedServiceDao::GDRIVE_SERVICE,
            $this->user_email
        );

        if ($service) {
            $this->__updateService($service);
        } else {
            $service = $this->__insertService();
        }

        $this->dao->setDefaultService($service);
    }

    /**
     * @param ConnectedServiceStruct $service
     *
     * @throws Exception
     * @throws \TypeError
     */
    private function __updateService(ConnectedServiceStruct $service): void
    {
        $this->dao->updateOauthToken($this->token, $service);

        $service->expired_at = null;
        $service->disabled_at = null;
        $this->dao->updateStruct($service);
    }

    /**
     * @return ConnectedServiceStruct
     * @throws Exception
     * @throws \TypeError
     */
    private function __insertService(): ConnectedServiceStruct
    {
        $service = new ConnectedServiceStruct([
            'uid' => $this->user->uid,
            'email' => $this->user_email,
            'name' => $this->user_name,
            'service' => ConnectedServiceDao::GDRIVE_SERVICE,
            'is_default' => 1,
            'created_at' => Utils::mysqlTimestamp(time())
        ]);
        $service->setEncryptedAccessToken($this->token);

        $lastId = $this->dao->insertStruct($service);

        if ($lastId === false) {
            throw new Exception('Unable to insert connected service');
        }

        return $this->dao->fetchById($lastId, ConnectedServiceStruct::class)
            ?? throw new Exception('Unable to retrieve inserted connected service');
    }

    /**
     * @param string $code
     *
     * @throws \Google\Service\Exception
     * @throws Exception
     * @throws TypeError
     */
    protected function __collectProperties(string $code): void
    {
        $gdriveClient = $this->googleClient ?? (new GoogleProvider)->getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");
        $gdriveClient->fetchAccessTokenWithAuthCode($code);
        $accessToken = $gdriveClient->getAccessToken();
        $this->token = is_array($accessToken)
            ? GDriveTokenHandler::accessTokenToJsonString($accessToken)
            : $accessToken;

        $infoService = new Google_Service_Oauth2($gdriveClient);
        $this->userInfo = $infoService->userinfo->get();

        $this->user_email = $this->userInfo['email'];
        $this->user_remote_id = $this->userInfo['id'];
        $this->user_name = $this->userInfo['name'];
    }

}
