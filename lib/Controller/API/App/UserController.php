<?php

namespace API\App;

use API\App\Json\ConnectedService;
use API\V2\AuthorizationError;
use API\V2\Json\Organization;
use API\V2\Json\User;
use API\V2\KleinController;

use ConnectedServices\ConnectedServiceDao ;
use ConnectedServices\ConnectedServiceStruct;
use Exceptions\NotFoundError;
use Exceptions\ValidationError;
use Organizations\MembershipDao;
use Organizations\OrganizationDao;
use Users_UserDao ;
use Utils ;

class UserController extends AbstractStatefulKleinController  {

    /**
     * @var \Users_UserStruct
     */
    protected $user ;
    protected $connectedServices ;

    public function show() {
        $metadata = $this->user->getMetadataAsKeyValue() ;

        // TODO: move this into a formatter class
        $this->response->json( array(
            'user' => User::renderItem( $this->user ),
            'connected_services' => ( new ConnectedService( $this->connectedServices ))->render(),

            // TODO: this is likely to be unsafe to be passed here without a whitelist.
            'metadata' =>  ( empty( $metadata ) ? NULL : $metadata ),

            'organizations' => ( new Organization() )->render(
                (new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->findUserOrganizations( $this->user )
            )

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
        \Bootstrap::sessionClose();
        $this->__findUser();
        $this->__findConnectedServices();
    }

    private function __findUser() {
        $dao = new Users_UserDao();

        if ( isset( $_SESSION['uid'] ) ) {
            $this->user = $dao->getByUid( $_SESSION['uid'] ) ;
        }

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