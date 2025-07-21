<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\RateLimiterTrait;
use Exception;
use Klein\Response;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\Authentication\PasswordRules;

class UserController extends AbstractStatefulKleinController {

    use RateLimiterTrait;
    use PasswordRules;

    /**
     * @return void
     */
    public function show() {
        if ( empty( $_SESSION[ 'user_profile' ] ) ) {
            $this->response->code( 401 );
        }
        $this->response->json( $_SESSION[ 'user_profile' ] );
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
     * @throws ValidationError
     * @throws Exception
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

            $this->validatePasswordRequirements( $new_password, $new_password_confirmation );

            $cpModel = new ChangePasswordModel( $this->user );
            $cpModel->changePassword( $old_password, $new_password );

            $this->broadcastLogout();

        } finally {
            $this->incrementRateLimitCounter( $this->user->email, '/api/app/user/password/change' );
        }

        $this->response->code( 200 );

    }

    /**
     * @return void
     */
    public function redeemProject() {
        $_SESSION[ 'redeem_project' ] = true;
        $this->response->code( 200 );
    }

    protected function afterConstruct() {
        $loginValidator = new LoginValidator( $this );
        $this->appendValidator( $loginValidator );
    }

}