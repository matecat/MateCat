<?php

namespace API\App;

use API\V2\KleinController;
use Exceptions\ValidationError;
use Monolog\Handler\Curl\Util;
use Symfony\Component\Config\Definition\Exception\Exception;
use Users\PasswordReset;
use Users\Signup ;
use FlashMessage ;

use Users\RedeemableProject ;

use AuthCookie ;

class SignupController extends AbstractStatefulKleinController  {

    public function create() {
        // TODO: filter input params
        $signup = new Signup( $this->request->param('user') );

        if ( $signup->valid() ) {
            $signup->process();
            $this->response->code( 200 ) ;
        }
        else {
            $this->response->code( 400 ) ;
            $this->response->json( array('error' => array(
                'message' => $signup->getError()
            )) ) ;
        }
    }

    public function confirm() {
        try {
            $user = Signup::confirm( $this->request->param('token') ) ;

            $project = new RedeemableProject($user, $_SESSION );
            $project->tryToRedeem() ;

            if ( $project->getDestinationURL() ) {
                $this->response->redirect( $project->getDestinationURL() ) ;
            } else {
                $this->response->redirect( $this->__flushWantedURL() ) ;
            }
        }
        catch( ValidationError $e ) {
            FlashMessage::set('confirmToken', $e->getMessage(), FlashMessage::ERROR );
            $this->response->redirect( $this->__flushWantedURL()  );
        }

    }

    public function redeemProject() {
        $_SESSION['redeem_project'] = TRUE ;
        $this->response->code( 200 ) ;
    }

    public function authForPasswordReset() {
        try {
            $reset = new PasswordReset( $this->request->param('token'), $_SESSION ) ;
            $reset->authenticateUser();

            $project = new RedeemableProject( $reset->getUser(), $_SESSION ) ;
            $project->tryToRedeem()  ;

            if ( $project->getDestinationURL() ) {
                $this->response->redirect( $project->getDestinationURL() ) ;
            }
            else {
                $this->response->redirect( $this->__flushWantedURL() ) ;
            }

            FlashMessage::set('popup', 'passwordReset', FlashMessage::SERVICE );
        }

        catch( ValidationError $e ) {
            FlashMessage::set('passwordReset', $e->getMessage(), FlashMessage::ERROR );

            $this->response->redirect( \Routes::appRoot() ) ;
        }
    }

    public function resendEmailConfirm() {
        Signup::resendEmailConfirm( $this->request->param('email') ) ;
        $this->response->code( 200 );
    }

    public function forgotPassword() {
        Signup::forgotPassword( $this->request->param('email'), $this->request->param('wanted_url') ) ;
        $this->response->code( 200 );

    }

    private function __flushWantedURL() {
        $url = isset( $_SESSION['wanted_url'] ) ? $_SESSION['wanted_url'] : \Routes::appRoot();
        unset($_SESSION['wanted_url']) ;

        return $url ;
    }

    protected function afterConstruct()
    {
        // TODO: Implement afterConstruct() method.
    }

}