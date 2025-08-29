<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 10/09/24
 * Time: 15:21
 *
 */

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\FlashMessage;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Traits\RateLimiterTrait;
use Exception;
use Klein\Response;
use Model\Users\Authentication\PasswordResetModel;
use Model\Users\Authentication\PasswordRules;
use Model\Users\Authentication\SignupModel;
use Predis\PredisException;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use Utils\Url\CanonicalRoutes;

class ForgotPasswordController extends AbstractStatefulKleinController {

    use RateLimiterTrait;
    use PasswordRules;

    /**
     * Step 1
     *
     * Sends a password reset email to the provided email address.
     *
     * @return void
     * @throws PredisException
     * @throws Exception
     */
    public function forgotPassword() {

        $checkRateLimitEmail = $this->checkRateLimitResponse( $this->response, $this->request->param( 'email' ) ?? "BLANK_EMAIL", '/api/app/user/forgot_password', 5 );
        $checkRateLimitIp    = $this->checkRateLimitResponse( $this->response, Utils::getRealIpAddr() ?? "127.0.0.1", '/api/app/user/forgot_password', 5 );

        if ( $checkRateLimitIp instanceof Response ) {
            $this->response = $checkRateLimitIp;

            return;
        }

        if ( $checkRateLimitEmail instanceof Response ) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        $filtered = filter_var_array(
                [
                        'email'      => $this->request->param( 'email' ),
                        'wanted_url' => $this->request->param( 'wanted_url' )
                ],
                [
                        'email'      => FILTER_SANITIZE_EMAIL,
                        'wanted_url' => [
                                'filter' => FILTER_CALLBACK, 'options' => function ( $wanted_url ) {
                                    $wanted_url = filter_var( $wanted_url, FILTER_SANITIZE_URL );

                                    return parse_url( $wanted_url )[ 'host' ] != parse_url( AppConfig::$HTTPHOST )[ 'host' ] ? AppConfig::$HTTPHOST : $wanted_url;
                                }
                        ]
                ] );

        $signupModel = new SignupModel( $filtered, $_SESSION );

        $doForgotPassword = $this->doForgotPassword( $signupModel );

        $this->incrementRateLimitCounter( $this->request->param( 'email' ) ?? "BLANK_EMAIL", '/api/app/user/forgot_password' );
        $this->incrementRateLimitCounter( Utils::getRealIpAddr() ?? "127.0.0.1", '/api/app/user/forgot_password' );

        $this->response->code( $doForgotPassword[ 'code' ] );
        $this->response->json( [
                'email'      => $signupModel->getParams()[ 'email' ],
                'wanted_url' => $signupModel->getParams()[ 'wanted_url' ],
                'errors'     => $doForgotPassword[ 'errors' ],
        ] );

    }

    /**
     *
     * Step 2
     *
     * Authenticates a user for a password reset.
     *
     * This method checks the rate limit, validates the user
     * and redirects the user to the desired URL if successful.
     * If an error occurs during the process, it increments the rate limit counter
     * and redirects the user to the application root.
     *
     * @throws PredisException
     * @throws Exception
     */
    public function authForPasswordReset() {
        try {
            $checkRateLimit = $this->checkRateLimitResponse( $this->response, $this->request->param( 'token' ), '/api/app/user/password_reset' );
            if ( $checkRateLimit instanceof Response ) {
                $this->response = $checkRateLimit;

                return;
            }

            $reset = new PasswordResetModel( $_SESSION, $this->request->param( 'token' ) );
            $reset->validateUser();
            $this->response->redirect( $reset->flushWantedURL() );

            FlashMessage::set( 'popup', 'passwordReset', FlashMessage::SERVICE );

        } catch ( ValidationError $e ) {

            $this->incrementRateLimitCounter( $this->request->param( 'token' ), '/api/app/user/password_reset' );
            FlashMessage::set( 'passwordReset', $e->getMessage(), FlashMessage::ERROR );
            $this->response->redirect( CanonicalRoutes::appRoot() );

        }
    }

    /**
     * Step 3
     *
     * Set the new password
     * @throws ValidationError
     */
    public function setNewPassword() {

        $reset                 = new PasswordResetModel( $_SESSION );
        $new_password          = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING );
        $password_confirmation = filter_var( $this->request->param( 'password_confirmation' ), FILTER_SANITIZE_STRING );
        $this->validatePasswordRequirements( $new_password, $password_confirmation );
        $reset->resetPassword( $new_password );
        $this->user = $reset->getUser();
        $this->broadcastLogout();

        $this->response->code( 200 );

    }

    /**
     * @param SignupModel $signupModel
     *
     * @return array
     */
    private function doForgotPassword( SignupModel $signupModel ): array {

        $params = $signupModel->getParams();

        $email      = $params[ 'email' ];
        $wanted_url = $params[ 'wanted_url' ];
        $errors     = [];
        $code       = 200;

        if ( !$email ) {
            $errors[] = 'email is a mandatory field.';
            $code     = 400;
        }

        if ( !$wanted_url ) {
            $errors[] = 'wanted_url is a mandatory field.';
            $code     = 400;
        }

        if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $errors[] = 'email is not valid.';
            $code     = 400;
        }

        if ( !filter_var( $wanted_url, FILTER_VALIDATE_URL ) ) {
            $errors[] = 'wanted_url is not a valid URL.';
            $code     = 400;
        }

        if ( empty( $errors ) ) {
            try {

                if ( !$signupModel->forgotPassword() ) {
                    LoggerFactory::doJsonLog( 'Failed attempt to recover password with email ' . $email );
                }

            } catch ( Exception $exception ) {
                $errors[] = 'Error updating database.';
                $code     = $exception->getCode() > 0 ? $exception->getCode() : 500;
            }
        }

        return [
                'errors' => $errors,
                'code'   => $code,
        ];

    }

}