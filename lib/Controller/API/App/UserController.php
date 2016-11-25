<?php

namespace API\App;

use API\App\Json\ConnectedService;
use API\V2\AuthorizationError;
use API\V2\KleinController;

use ConnectedServices\ConnectedServiceDao ;
use ConnectedServices\ConnectedServiceStruct;
use Exceptions\NotFoundError;
use Exceptions\ValidationError;
use Users_UserDao ;
use Utils ;

class UserController extends AbstractStatefulKleinController  {

    /**
     * @var \Users_UserStruct
     */
    protected $user ;
    protected $connectedServices ;

    public function show() {
        // TODO: move this into a formatter class
        $this->response->json( array(
            'user' => array(
                'uid' => $this->user->uid,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'has_password' => !is_null($this->user->pass)
            ),
            'connected_services' => ( new ConnectedService( $this->connectedServices ))->render()
        ));
    }

    public function updatePassword() {
        $new_password = filter_var($this->request->param('password'), FILTER_SANITIZE_STRING );

        \Users_UserValidator::validatePassword( $new_password );

        $this->user->pass = Utils::encryptPass( $new_password, $this->user->salt ) ;
        \Users_UserDao::updateStruct( $this->user, array('fields' => array('pass') ) ) ;

        $this->response->code( 200 ) ;
    }

    protected function afterConstruct() {
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