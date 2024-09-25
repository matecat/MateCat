<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/11/2016
 * Time: 18:39
 */

namespace ConnectedServices;

use Exception;
use Google_Client;

class GDriveTokenVerifyModel {

    protected $service;
    protected $expired;
    protected $refreshed;

    public function __construct( ConnectedServiceStruct $service ) {
        $this->service = $service;
    }

    public function validOrRefreshed( Google_Client $gClient ): bool {
        $this->refreshed           = false;
        $this->expired             = false;
        $decryptedOauthAccessToken = $this->service->getDecryptedOauthAccessToken();

        if ( false === $decryptedOauthAccessToken ) {
            $this->__expireService();

            return false;
        }

        try {
            $newToken = GDrive::getsNewToken( $gClient, $decryptedOauthAccessToken );
        } catch ( Exception $e ) {
            $this->__expireService();

            return false;
        }

        if ( $newToken ) {
            $this->__updateToken( $newToken );
        }

        return true;
    }

    public function getService() {
        return $this->service;
    }

    private function __expireService() {
        $dao = new ConnectedServiceDao();
        $dao->setServiceExpired( time(), $this->service );

        $this->expired = true;
    }

    private function __updateToken( $newToken ) {
        $dao           = new ConnectedServiceDao();
        $this->service = $dao->updateOauthToken( $newToken, $this->service );

        $this->refreshed = true;
    }
}