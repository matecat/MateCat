<?php

namespace API\App;

use API\App\Json\ConnectedService;
use API\App\Json\UserProfile;
use API\V2\Validators\LoginValidator;
use ConnectedServices\ConnectedServiceDao;
use ConnectedServices\ConnectedServiceStruct;
use Exception;
use Exceptions\ValidationError;
use TeamModel;
use Teams\MembershipDao;
use Teams\TeamStruct;
use Users\ChangePasswordModel;
use Users_UserStruct;

class UserController extends AbstractStatefulKleinController {

    /**
     * @var Users_UserStruct
     */
    protected $user;

    /**
     * @var ConnectedServiceStruct[]
     */
    protected $connectedServices;

    public function show() {
        $metadata = $this->user->getMetadataAsKeyValue();

        $membersDao = new MembershipDao();
        $userTeams  = array_map(
                function ( $team ) use ( $membersDao ) {
                    $teamModel = new TeamModel( $team );
                    $teamModel->updateMembersProjectsCount();

                    /** @var $team TeamStruct */
                    return $team;
                },
                $membersDao->findUserTeams( $this->user )
        );

        $this->response->json( ( new UserProfile() )->renderItem(
                $this->user,
                $userTeams,
                $this->connectedServices,
                $metadata
        ) );

    }

    /**
     * @throws ValidationError
     * @throws Exception
     */
    public function changePasswordAsLoggedUser() {

        $old_password              = filter_var( $this->request->param( 'old_password' ), FILTER_SANITIZE_STRING );
        $new_password              = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING );
        $new_password_confirmation = filter_var( $this->request->param( 'password_confirmation' ), FILTER_SANITIZE_STRING );

        $cpModel = new ChangePasswordModel( $this->user );
        $cpModel->changePassword( $old_password, $new_password, $new_password_confirmation );

        $this->response->code( 200 );

    }

    protected function afterConstruct() {
        $loginValidator = new LoginValidator( $this );
        $loginValidator->onSuccess( function () {
            $this->__findConnectedServices();
        } );
        $this->appendValidator( $loginValidator );
    }

    private function __findConnectedServices() {
        $dao      = new ConnectedServiceDao();
        $services = $dao->findServicesByUser( $this->user );
        if ( !empty( $services ) ) {
            $this->connectedServices = $services;
        }

    }

}