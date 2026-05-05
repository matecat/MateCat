<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/11/2016
 * Time: 18:39
 */

namespace Model\ConnectedServices\GDrive;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Google_Client;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;

class GDriveTokenVerifyModel
{

    protected ConnectedServiceStruct $service;
    protected bool $expired;
    protected $refreshed;

    public function __construct(ConnectedServiceStruct $service)
    {
        $this->service = $service;
    }

    /**
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     */
    public function validOrRefreshed(Google_Client $gClient): bool
    {
        $this->refreshed = false;
        $this->expired = false;
        $decryptedOauthAccessToken = $this->service->getDecryptedOauthAccessToken();

        if (false === $decryptedOauthAccessToken) {
            $this->__expireService();

            return false;
        }

        try {
            $newToken = GDriveTokenHandler::getNewToken($gClient, $decryptedOauthAccessToken);
        } catch (Exception) {
            $this->__expireService();

            return false;
        }

        if ($newToken) {
            $this->__updateToken($newToken);
        }

        return true;
    }

    public function getService(): ConnectedServiceStruct
    {
        return $this->service;
    }

    /**
     * @throws Exception
     */
    private function __expireService(): void
    {
        $dao = new ConnectedServiceDao();
        $dao->setServiceExpired(time(), $this->service);

        $this->expired = true;
    }

    /**
     * @throws Exception
     */
    private function __updateToken(string $newToken): void
    {
        $dao = new ConnectedServiceDao();
        $this->service = $dao->updateOauthToken($newToken, $this->service);

        $this->refreshed = true;
    }
}