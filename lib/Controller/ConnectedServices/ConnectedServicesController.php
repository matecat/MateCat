<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/11/2016
 * Time: 16:01
 */

namespace ConnectedServices;


use API\App\Json\ConnectedService;
use API\V2\KleinController;
use ConnectedServices\ConnectedServiceDao;
use ConnectedServices\ConnectedServiceStruct;
use ConnectedServices\GDrive;

class ConnectedServicesController extends KleinController {

    /**
     * @var \Users_UserStruct
     */
    protected $user ;

    /**
     * @var ConnectedServiceStruct
     */
    protected $service ;

    public function verify() {
        $this->__validateOwnership();

        if ( $this->service->service == ConnectedServiceDao::GDRIVE_SERVICE ) {
            $this->__handleGDrive();
        }
    }

    protected function afterConstruct() {
        \Bootstrap::sessionStart() ;

        $this->response->noCache();
    }

    private function __handleGDrive() {
        $verifier = new GDriveTokenVerifyModel( $this->service ) ;

        if ( $verifier->validOrRefreshed() ) {
            $this->response->code( 200 ) ;
        }
        else {
            $this->response->code( 403 ) ;
        }

        $formatter = new ConnectedService( array() );
        $this->response->json( array( 'connected_service' => $formatter->renderItem( $this->service ) ) ) ;
    }

    private function __validateOwnership() {

        // check for the user to be logged
        $userDao = new \Users_UserDao() ;
        $this->user = $userDao->getByUid( $_SESSION['uid'] );

        if ( !$this->user ) {
            throw new \Exceptions_RecordNotFound('user not found') ;
        }

        $serviceDao = new ConnectedServiceDao();
        $this->service = $serviceDao->findServiceByUserAndId( $this->user, $this->request->param('id_service') );

        if ( !$this->service ) {
            throw new \Exceptions_RecordNotFound( 'service not found' ) ;
        }
    }
}