<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\FlashMessage;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Traits\RateLimiterTrait;
use Controller\Views\CustomPageView;
use Exception;
use Klein\Response;
use Model\Teams\InvitedUser;
use Model\Users\Authentication\PasswordRules;
use Model\Users\Authentication\SignupModel;
use Model\Users\RedeemableProject;
use Utils\Registry\AppConfig;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;

class SignupController extends AbstractStatefulKleinController {

    use RateLimiterTrait;
    use PasswordRules;

    /**
     * @throws Exception
     */
    public function create() {

        $user = $this->validateCreationRequest();

        $userIp = Utils::getRealIpAddr();

        // rate limit on email
        $checkRateLimitOnEmail = $this->checkRateLimitResponse( $this->response, $user[ 'email' ], '/api/app/user', 3 );
        if ( $checkRateLimitOnEmail instanceof Response ) {
            $this->response = $checkRateLimitOnEmail;

            return;
        }

        // rate limit on IP
        $checkRateLimitOnIp = $this->checkRateLimitResponse( $this->response, $userIp, '/api/app/user', 3 );
        if ( $checkRateLimitOnIp instanceof Response ) {
            $this->response = $checkRateLimitOnIp;

            return;
        }

        $signup = new SignupModel( $user, $_SESSION );
        $this->incrementRateLimitCounter( $userIp, '/api/app/user' );
        $this->incrementRateLimitCounter( $user[ 'email' ], '/api/app/user' );

        $signup->processSignup();
        $this->response->code( 200 );

    }

    /**
     * @throws ValidationError
     */
    private function validateCreationRequest(): array {

        $user = filter_var_array(
                (array)$this->request->param( 'user' ),
                [
                        'email'                 => [ 'filter' => FILTER_SANITIZE_EMAIL, 'options' => [] ],
                        'password'              => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                        'password_confirmation' => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                        'first_name'            => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $firstName ) {
                                    return CatUtils::stripMaliciousContentFromAName( $firstName );
                                }
                        ],
                        'last_name'             => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $lastName ) {
                                    return CatUtils::stripMaliciousContentFromAName( $lastName );
                                }
                        ],
                        'wanted_url'            => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $wanted_url ) {
                                    $wanted_url = filter_var( $wanted_url, FILTER_SANITIZE_URL );

                                    return parse_url( $wanted_url )[ 'host' ] != parse_url( AppConfig::$HTTPHOST )[ 'host' ] ? AppConfig::$HTTPHOST : $wanted_url;
                                }
                        ]
                ]
        );

        if ( empty( $user[ 'email' ] ) ) {
            throw new ValidationError( 'Missing email' );
        }

        if ( empty( $user[ 'first_name' ] ) ) {
            throw new ValidationError( "First name must contain at least one letter" );
        }

        if ( empty( $user[ 'last_name' ] ) ) {
            throw new ValidationError( "Last name must contain at least one letter" );
        }

        $this->validatePasswordRequirements( $user[ 'password' ], $user[ 'password_confirmation' ] );

        return $user;

    }

    /**
     * @throws Exception
     */
    public function confirm() {

        $token = filter_var( $this->request->param( 'token' ), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );

        $signupModel = new SignupModel( [ 'token' => $token ], $_SESSION );

        try {

            $user = $signupModel->confirm();

            AuthCookie::setCredentials( $user );
            AuthenticationHelper::getInstance( $_SESSION );

            if ( InvitedUser::hasPendingInvitations() ) {
                InvitedUser::completeTeamSignUp( $user, $_SESSION[ 'invited_to_team' ] );
            }

            $project = new RedeemableProject( $user, $_SESSION );
            $project->tryToRedeem();

            if ( $project->getDestinationURL() ) {
                $this->response->redirect( $project->getDestinationURL() );
            } else {
                $this->response->redirect( $signupModel->flushWantedURL() );
            }

            FlashMessage::set( 'popup', 'profile', FlashMessage::SERVICE );
        } catch ( Exception $e ) {
            FlashMessage::set( 'confirmToken', $e->getMessage(), FlashMessage::ERROR );

            // return a 410 status code
            $controllerInstance = new CustomPageView();
            $controllerInstance->setView( '410.html', [], 410 );
            $controllerInstance->render();

        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function resendConfirmationEmail() {

        $userIp = Utils::getRealIpAddr();

        // rate limit on email
        $checkRateLimitOnEmail = $this->checkRateLimitResponse( $this->response, $this->request->param( 'email' ), '/api/app/user', 3 );
        if ( $checkRateLimitOnEmail instanceof Response ) {
            $this->response = $checkRateLimitOnEmail;

            return;
        }

        // rate limit on IP
        $checkRateLimitOnIp = $this->checkRateLimitResponse( $this->response, $userIp, '/api/app/user', 3 );
        if ( $checkRateLimitOnIp instanceof Response ) {
            $this->response = $checkRateLimitOnIp;

            return;
        }

        $this->incrementRateLimitCounter( $userIp, '/api/app/user' );
        $this->incrementRateLimitCounter( $this->request->param( 'email' ), '/api/app/user' );

        SignupModel::resendConfirmationEmail( $this->request->param( 'email' ) );
        $this->response->code( 200 );

    }

}