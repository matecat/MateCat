<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/11/2016
 * Time: 11:54
 */

namespace ConnectedServices;

use ConnectedServices\GDrive\GoogleClientFactory;
use Google_Service_Oauth2;
use Utils;

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

    /**
     * Updates or creates the service record.
     *
     * If the record does not exist, it is created.
     * If the record exists, it is updated.
     *
     * In the process, the current service becomes the default.
     * `is_default` flag from the other ones is removed.
     *
     * @param $code
     *
     * @throws \Exceptions\ValidationError
     */
    public function updateOrCreateRecordByCode( $code ) {
        $this->__collectProperties( $code );

        // We have the user info email and name, we can save it along with the gdrive token to identify it.
        \Log::doJsonLog( $this->token ) ;
        \Log::doJsonLog( $this->userInfo ) ;

        $dao = new ConnectedServiceDao();
        $service = $dao->findUserServicesByNameAndEmail(
            $this->user, ConnectedServiceDao::GDRIVE_SERVICE, $this->user_email
        );

        if ( $service ) {
            $this->__updateService($service);
        }
        else {
            $service = $this->__insertService();
        }

        $dao->setDefaultService( $service );
    }

    /**
     * @param ConnectedServiceStruct $service
     *
     * @throws \Exception
     */
    private function __updateService(ConnectedServiceStruct $service ) {
        $dao = new ConnectedServiceDao() ;
        $dao->updateOauthToken( $this->token, $service ) ;

        $service->expired_at = null;
        $service->disabled_at = null;
        $dao->updateStruct( $service ) ;
    }

    private function __insertService() {
        $service = new ConnectedServiceStruct(array(
            'uid' => $this->user->uid,
            'email' => $this->user_email,
            'name' => $this->user_name,
            'service' => ConnectedServiceDao::GDRIVE_SERVICE,
            'is_default' => 1,
            'created_at' => Utils::mysqlTimestamp( time() )
        ));
        $service->setEncryptedAccessToken( $this->token ) ;
        $dao = new ConnectedServiceDao();

        $lastId = $dao->insertStruct( $service ) ;

        return $dao->findById( $lastId ) ;
    }

    /**
     * @param $code
     */
    private function __collectProperties( $code ) {
        $gdriveClient = GoogleClientFactory::create();
        $gdriveClient->authenticate($code);
        $this->token = $gdriveClient->getAccessToken();

        if ( is_array( $this->token ) ) {
            // Enforce token to be passed passed around as json_string, to favour encryption and storage.
            // Prevent slash escape, see: http://stackoverflow.com/a/14419483/1297909
            $this->token = GDrive::accessTokenToJsonString( $this->token ) ;
        }

        $infoService = new Google_Service_Oauth2($gdriveClient);
        $this->userInfo = $infoService->userinfo->get();

        $this->user_email       = $this->userInfo['email'];
        $this->user_remote_id   = $this->userInfo['id'];
        $this->user_name        = $this->userInfo['name'];
    }

}