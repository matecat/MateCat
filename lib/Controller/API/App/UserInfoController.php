<?php

namespace API\App;

use API\App\Json\ConnectedService;
use API\V2\AuthorizationError;
use API\V2\KleinController;

use ConnectedServices\ConnectedServiceDao ;
use ConnectedServices\ConnectedServiceStruct;
use Exceptions\NotFoundError;
use Users_UserDao ;

class UserInfoController extends KleinController  {

    protected $user ;
    protected $connectedServices ;

    public function show() {
        // TODO: move this into a formatter class
        $this->response->json( array(
            'user' => array(
                'uid' => $this->user->uid,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email
            ),
            'connected_services' => ( new ConnectedService( $this->connectedServices ))->render()
        ));
    }

    protected function afterConstruct() {
        \Bootstrap::sessionStart();

        if ( !isset( $_SESSION['uid'] ) ) {
            throw new NotFoundError('user session not found');
        }

        $this->__findUser();
        $this->__findConnectedServices();

    }

    private function __findUser() {
        $dao = new Users_UserDao();
        $this->user = $dao->getByUid( $_SESSION['uid'] ) ;
        if (!$this->user) {
            throw new NotFoundError('user not found');
        }
    }

    private function __findConnectedServices() {
        $dao = new ConnectedServiceDao();
        $services = $dao->findServicesByUser($this->user);
        if ( !empty( $services ) ) {
            $this->connectedServices = $services ;
        }

    }

}