<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/11/2016
 * Time: 18:39
 */

namespace ConnectedServices;

class GDriveTokenVerifyModel {

    protected $service ;
    protected $expired;
    protected $refreshed ;

    public function __construct( ConnectedServiceStruct $service ) {
        $this->service = $service ;
    }

    public function validOrRefreshed() {
        $this->refreshed = false ;
        $this->expired = false ;
        $decryptedOauthAccessToken = $this->service->getDecryptedOauthAccessToken();

        if(false === $decryptedOauthAccessToken){
            $this->__expireService();

            return false;
        }

        try {
            $newToken = GDrive::getsNewToken( $decryptedOauthAccessToken );
        } catch(\Exception $e ) {

            if ( preg_match( '/invalid_grant/', $e->getMessage() ) ) {
                $this->__expireService(); ;
                return false;
            }
            else {
                // TODO: handle different cases as they appear, we don't know
                // yet all things that can go wrong.
                throw $e ;
            }
        }

        if ( $newToken ) {
            $this->__updateToken($newToken);
        }

        return true;
    }

    public function getService() {
        return $this->service ;
    }

    private function __expireService() {
        $dao = new ConnectedServiceDao() ;
        $dao->setServiceExpired( time(), $this->service ) ;

        $this->expired = true ;
    }

    private function __updateToken($newToken) {
        $dao = new ConnectedServiceDao();
        $this->service = $dao->updateOauthToken( $newToken, $this->service ) ;

        $this->refreshed = true ;
    }
}