<?php

namespace API\App\Authentication;

use API\App\RateLimiterTrait;
use API\Commons\AbstractStatefulKleinController;
use API\Commons\Authentication\AuthCookie;
use API\Commons\Authentication\AuthenticationHelper;
use CatUtils;
use CustomPageView;
use Exception;
use FlashMessage;
use INIT;
use Klein\Response;
use Teams\InvitedUser;
use Users\Authentication\SignupModel;
use Users\RedeemableProject;
use Utils;

class SignupController extends AbstractStatefulKleinController {

    use RateLimiterTrait;

    /**
     * @throws Exception
     */
    public function create() {

        $user = filter_var_array(
                (array)$this->request->param( 'user' ),
                [
                        'email'                 => [ 'filter' => FILTER_SANITIZE_EMAIL, 'options' => [] ],
                        'password'              => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                        'password_confirmation' => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                        'first_name'            => [
                            'filter' => FILTER_CALLBACK, 'options' => function ( $firstName ) {
                                return CatUtils::stripMaliciousContentFromAName($firstName);
                            }
                        ],
                        'last_name'             => [
                            'filter' => FILTER_CALLBACK, 'options' => function ( $lastName ) {
                                return CatUtils::stripMaliciousContentFromAName($lastName);
                            }
                        ],
                        'wanted_url'            => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $wanted_url ) {
                                    $wanted_url = filter_var( $wanted_url, FILTER_SANITIZE_URL );

                                    return parse_url( $wanted_url )[ 'host' ] != parse_url( INIT::$HTTPHOST )[ 'host' ] ? INIT::$HTTPHOST : $wanted_url;
                                }
                        ]
                ]
        );

        if(empty($user['email'])){
            $this->response->code( 400 );
            $this->response->json( [
                'error' => [
                    'message' => "Missing email"
                ]
            ] );
            exit();
        }

        if(empty($user['first_name'])){
            $this->response->code( 400 );
            $this->response->json( [
                'error' => [
                    'message' => "First name must contain at least one letter"
                ]
            ] );
            exit();
        }

        if(empty($user['last_name'])){
            $this->response->code( 400 );
            $this->response->json( [
                'error' => [
                    'message' => "Last name must contain at least one letter"
                ]
            ] );
            exit();
        }

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

        // email
        if ( $signup->valid() ) {
            $signup->processSignup();
            $this->response->code( 200 );
        } else {
            $this->response->code( 400 );
            $this->response->json( [
                    'error' => [
                            'message' => $signup->getError()
                    ]
            ] );
        }
    }

    /**
     * @throws Exception
     */
    public function confirm() {

        $signupModel = new SignupModel( [ 'token' => $this->request->param( 'token' ) ], $_SESSION );

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
            $controllerInstance->renderAndClose();

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