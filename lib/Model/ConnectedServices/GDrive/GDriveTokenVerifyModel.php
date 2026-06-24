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
use TypeError;

class GDriveTokenVerifyModel
{

    protected ConnectedServiceStruct $service;
    protected bool $expired;
    protected bool $refreshed;
    protected ?ConnectedServiceDao $dao = null;

    public function __construct(ConnectedServiceStruct $service, ?ConnectedServiceDao $dao = null)
    {
        $this->service = $service;
        $this->dao = $dao;
    }

    /**
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws TypeError
     */
    public function validOrRefreshed(Google_Client $gClient): bool
    {
        $this->refreshed = false;
        $this->expired = false;
        $decryptedOauthAccessToken = $this->service->getDecryptedOauthAccessToken();

        if (null === $decryptedOauthAccessToken) {
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
        $this->dao ??= new ConnectedServiceDao();
        $this->dao->setServiceExpired(time(), $this->service);

        $this->expired = true;
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function __updateToken(string $newToken): void
    {
        $this->dao ??= new ConnectedServiceDao();
        $this->service = $this->dao->updateOauthToken($newToken, $this->service);

        $this->refreshed = true;
    }
}