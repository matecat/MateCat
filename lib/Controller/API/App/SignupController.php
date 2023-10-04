<?php

namespace API\App;

use Exception;
use Exceptions\ValidationError;
use FlashMessage;
use Log;
use Routes;
use Teams\InvitedUser;
use Users\PasswordResetModel;
use Users\RedeemableProject;
use Users\SignupModel;

class SignupController extends AbstractStatefulKleinController {

    public function create() {

        $signup = new SignupModel( $this->request->param( 'user' ) );

        if ( $signup->valid() ) {
            $signup->process();
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

    public function confirm() {
        try {
            $user = SignupModel::confirm( $this->request->param( 'token' ) );

            if ( InvitedUser::hasPendingInvitations() ) {
                InvitedUser::completeTeamSignUp( $user, $_SESSION[ 'invited_to_team' ] );
            }

            $project = new RedeemableProject( $user, $_SESSION );
            $project->tryToRedeem();

            if ( $project->getDestinationURL() ) {
                $this->response->redirect( $project->getDestinationURL() );
            } else {
                $this->response->redirect( $this->__flushWantedURL() );
            }

            FlashMessage::set( 'popup', 'profile', FlashMessage::SERVICE );
        } catch ( ValidationError $e ) {
            FlashMessage::set( 'confirmToken', $e->getMessage(), FlashMessage::ERROR );
            $this->response->redirect( $this->__flushWantedURL() );
        }

    }

    public function redeemProject() {
        $_SESSION[ 'redeem_project' ] = true;
        $this->response->code( 200 );
    }

    public function authForPasswordReset() {
        try {

            $reset = new PasswordResetModel( $this->request->param( 'token' ), $_SESSION );
            $reset->validateUser();
            $this->response->redirect( $this->__flushWantedURL() );

            FlashMessage::set( 'popup', 'passwordReset', FlashMessage::SERVICE );

        } catch ( ValidationError $e ) {

            FlashMessage::set( 'passwordReset', $e->getMessage(), FlashMessage::ERROR );
            $this->response->redirect( Routes::appRoot() );

        }
    }

    public function resendEmailConfirm() {
        SignupModel::resendEmailConfirm( $this->request->param( 'email' ) );
        $this->response->code( 200 );
    }

    public function forgotPassword() {

        $doForgotPassword = $this->doForgotPassword();

        $this->response->code( empty( $doForgotPassword ) ? 200 : 500 );
        $this->response->json( [
                'email'      => $this->request->param( 'email' ),
                'wanted_url' => $this->request->param( 'wanted_url' ),
                'errors'     => $doForgotPassword,
        ] );
    }

    /**
     * @return array
     */
    private function doForgotPassword() {

        $email      = $this->request->param( 'email' );
        $wanted_url = $this->request->param( 'wanted_url' );
        $errors     = [];

        if ( !$email ) {
            $errors[] = 'email is a mandatary field.';
        }

        if ( !$wanted_url ) {
            $errors[] = 'wanted_url is a mandatary field.';
        }

        if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $errors[] = 'email is not valid.';
        }

        if ( !filter_var( $wanted_url, FILTER_VALIDATE_URL ) ) {
            $errors[] = 'wanted_url is not a valid URL.';
        }

        if ( empty( $errors ) ) {
            try {
                if ( !SignupModel::forgotPassword( $email, $wanted_url ) ) {
                    Log::doJsonLog( 'Failed attempt to recover password with email ' . $email );
                }
            } catch ( Exception $exception ) {
                $errors[] = 'Error updating database.';
            }
        }

        return $errors;
    }


    /**
     * @throws ValidationError
     */
    public function setNewPassword() {

        $reset                 = new PasswordResetModel( null, $_SESSION );
        $new_password          = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING );
        $password_confirmation = filter_var( $this->request->param( 'password_confirmation' ), FILTER_SANITIZE_STRING );
        $reset->resetPassword( $new_password, $password_confirmation );

        $this->response->code( 200 );

    }

    private function __flushWantedURL() {
        $url = isset( $_SESSION[ 'wanted_url' ] ) ? $_SESSION[ 'wanted_url' ] : Routes::appRoot();
        unset( $_SESSION[ 'wanted_url' ] );

        return $url;
    }

}