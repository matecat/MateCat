<?php

namespace API\App;

use API\App\Json\UserProfile;
use API\V2\Validators\LoginValidator;
use ConnectedServices\ConnectedServiceDao;
use ConnectedServices\ConnectedServiceStruct;
use Exception;
use Exceptions\ValidationError;
use Klein\Response;
use TeamModel;
use Teams\MembershipDao;
use Teams\TeamStruct;
use Users\ChangePasswordModel;
use Users_UserStruct;

class UserController extends AbstractStatefulKleinController {

    use RateLimiterTrait;

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
     * Changes the password of a logged-in user.
     *
     * This method first checks if the rate limit for changing password has been reached. If the limit has been
     * reached, the method returns without performing any password change.
     *
     * The old password, new password, and password confirmation are retrieved from the request parameters and
     * then sanitized using FILTER_SANITIZE_STRING. The sanitized values are then passed to the `changePassword()`
     * method of the `ChangePasswordModel` object.
     *
     * After changing the password, it increments the rate limit counter for the user's email
     * * and sets the response code to 200.
     *
     * The HTTP response code is set to 200 upon successful password change.
     *
     * @return void
     * @throws Exception
     * @throws ValidationError
     */
    public function changePasswordAsLoggedUser() {

        $checkRateLimitEmail = $this->checkRateLimitResponse( $this->response, $this->user->email, '/api/app/user/password/change', 5 );
        if ( $checkRateLimitEmail instanceof Response ) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        $old_password              = filter_var( $this->request->param( 'old_password' ), FILTER_SANITIZE_STRING );
        $new_password              = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING );
        $new_password_confirmation = filter_var( $this->request->param( 'password_confirmation' ), FILTER_SANITIZE_STRING );

        try {
            $cpModel = new ChangePasswordModel( $this->user );
            $cpModel->changePassword( $old_password, $new_password, $new_password_confirmation );
        } finally {
            $this->incrementRateLimitCounter( $this->user->email, '/api/app/user/password/change' );
        }

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