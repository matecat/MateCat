<?php

namespace API\App\Authentication;

use API\App\RateLimiterTrait;
use API\Commons\AbstractStatefulKleinController;
use Exception;
use Exceptions\ValidationError;
use FlashMessage;
use INIT;
use Klein\Response;
use Teams\InvitedUser;
use Users\RedeemableProject;
use Users\SignupModel;
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
                        'email'                 => FILTER_SANITIZE_EMAIL,
                        'password'              => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                        'password_confirmation' => [ 'filter' => FILTER_SANITIZE_STRING, 'options' => FILTER_FLAG_STRIP_LOW ],
                        'first_name'            => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $username ) {
                                    return mb_substr( preg_replace( '/(?:https?|s?ftp)?\P{L}+/', '', $username ), 0, 50 );
                                }
                        ],
                        'last_name'             => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $username ) {
                                    return mb_substr( preg_replace( '/(?:https?|s?ftp)?\P{L}+/', '', $username ), 0, 50 );
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
        try {

            $signupModel = new SignupModel( [ 'token' => $this->request->param( 'token' ) ], $_SESSION );
            $user        = $signupModel->confirm();

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
        } catch ( ValidationError $e ) {
            FlashMessage::set( 'confirmToken', $e->getMessage(), FlashMessage::ERROR );
            $this->response->redirect( $signupModel->flushWantedURL() );
        }

    }

    /**
     * @return void
     */
    public function resendConfirmationEmail() {
        SignupModel::resendConfirmationEmail( $this->request->param( 'email' ) );
        $this->response->code( 200 );
    }

}