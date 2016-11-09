<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/11/2016
 * Time: 11:54
 */

namespace ConnectedServices;

use Google_Service_Oauth2 ;
use Utils ;

class GDriveUserAuthorizationModel {

    protected $user ;

    protected $userInfo ;
    protected $token ;

    protected $user_email ;
    protected $user_remote_id ;
    protected $user_name ;

    public function __construct(\Users_UserStruct $user) {
       $this->user = $user ;
    }

    public function updateOrCreateRecordByCode( $code ) {
        $this->__collectProperties( $code );

        // We have the user info email and name, we can save it along with the gdrive token to identify it.
        \Log::doLog( $this->token ) ;
        \Log::doLog( $this->userInfo ) ;

        $dao = new ConnectedServiceDao();
        $service = $dao->findUserServicesByNameAndEmail(
            $this->user, ConnectedServiceDao::GDRIVE_SERVICE, $this->user_email
        );

        if ( $service ) {
            $this->__updateService($service);
        }
        else {
            $this->__insertService();
        }
    }

    private function __updateService(ConnectedServiceStruct $service ) {
        $service->setEncryptedAccessToken( $this->token );
        $service->updated_at = Utils::mysqlTimestamp( time() ) ;
        $service->disabled_at = null;

        $dao = new ConnectedServiceDao() ;
        $dao->updateStruct( $service ) ;
    }

    private function __insertService() {
        $service = new ConnectedServiceStruct(array(
            'uid' => $this->user->uid,
            'email' => $this->user_email,
            'name' => $this->user_name,
            'service' => ConnectedServiceDao::GDRIVE_SERVICE,
            'created_at' => Utils::mysqlTimestamp( time() )
        ));
        $service->setEncryptedAccessToken( $this->token ) ;
        $dao = new ConnectedServiceDao();
        $dao->insertStruct( $service ) ;

    }

    private function __collectProperties( $code ) {
        $gdriveClient = GDrive::getClient();
        $gdriveClient->authenticate($code);
        $this->token = $gdriveClient->getAccessToken();

        $infoService = new Google_Service_Oauth2($gdriveClient);
        $this->userInfo = $infoService->userinfo->get();

        $this->user_email       = $this->userInfo['email'];
        $this->user_remote_id   = $this->userInfo['id'];
        $this->user_name        = $this->userInfo['name'];
    }

}