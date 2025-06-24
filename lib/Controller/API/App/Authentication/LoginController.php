<?php
/**
 * Created by PhpStorm.
 * User: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 19/09/2024
 * Time: 09:38
 */

namespace API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Traits\RateLimiterTrait;
use CookieManager;
use Exception;
use INIT;
use Klein\Response;
use SimpleJWT;
use Users\RedeemableProject;
use Users_UserDao;
use Utils;

class LoginController extends AbstractStatefulKleinController {

    use RateLimiterTrait;

    public function directLogout() {
        $this->logout();
        $this->response->code( 200 );
    }

    /**
     * @throws Exception
     */
    public function login() {

        $params = filter_var_array( $this->request->params(), [
                'email'    => FILTER_SANITIZE_EMAIL,
                'password' => FILTER_SANITIZE_STRING
        ] );

        $checkRateLimitResponse = $this->checkRateLimitResponse( $this->response, $params[ 'email' ] ?? 'BLANK_EMAIL', '/api/app/user/login', 5 );
        $checkRateLimitIp       = $this->checkRateLimitResponse( $this->response, Utils::getRealIpAddr() ?? "127.0.0.1", '/api/app/user/login', 5 );

        if ( $checkRateLimitResponse instanceof Response ) {
            $this->response = $checkRateLimitResponse;

            return;
        }

        if ( $checkRateLimitIp instanceof Response ) {
            $this->response = $checkRateLimitIp;

            return;
        }

        // XSRF-Token
        $xsrfToken = $this->request->headers()->get( INIT::$XSRF_TOKEN );

        if ( $xsrfToken === null ) {
            $this->incrementRateLimitCounter( $params[ 'email' ] ?? 'BLANK_EMAIL', '/api/app/user/login' );
            $this->incrementRateLimitCounter( Utils::getRealIpAddr() ?? "127.0.0.1", '/api/app/user/login' );
            $this->response->code( 403 );

            return;
        }

        try {
            SimpleJWT::getValidPayload( $xsrfToken );
        } catch ( Exception $exception ) {
            $this->incrementRateLimitCounter( $params[ 'email' ] ?? 'BLANK_EMAIL', '/api/app/user/login' );
            $this->incrementRateLimitCounter( Utils::getRealIpAddr() ?? "127.0.0.1", '/api/app/user/login' );
            $this->response->code( 403 );

            return;
        }

        CookieManager::setCookie( INIT::$XSRF_TOKEN, '',
                [
                        'expires' => 0,
                        'path'    => '/',
                        'domain'  => INIT::$COOKIE_DOMAIN
                ]
        );

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( $params[ 'email' ] );

        if ( $user && $user->passwordMatch( $params[ 'password' ] ) && !is_null( $user->email_confirmed_at ) ) {

            $user->clearAuthToken();

            $dao->updateUser( $user );
            $dao->destroyCacheByUid( $user->uid );

            $project = new RedeemableProject( $user, $_SESSION );
            $project->tryToRedeem();

            AuthCookie::setCredentials( $user );
            AuthenticationHelper::getInstance( $_SESSION );

            $this->response->code( 200 );

        } else {
            $this->incrementRateLimitCounter( $params[ 'email' ], '/api/app/user/login' );
            $this->incrementRateLimitCounter( Utils::getRealIpAddr(), '/api/app/user/login' );
            $this->response->code( 404 );
        }

    }

    /**
     * Signed Double-Submit Cookie
     * @throws Exception
     */
    public function token() {
        $jwt = new SimpleJWT( [ "csrf" => Utils::uuid4() ] );
        $jwt->setTimeToLive( 60 );

        CookieManager::setCookie( INIT::$XSRF_TOKEN, $jwt->jsonSerialize(),
                [
                        'expires'  => time() + 60, /* now + 60 seconds */
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => false,
                        'samesite' => 'Strict',
                ]
        );

        $this->response->code( 200 );
    }

    /**
     * Signed Double-Submit Cookie
     * @throws Exception
     */
    public function socketToken() {

        if ( empty( $_SESSION[ 'user' ] ) ) {
            $this->response->code( 406 );

            return;
        }

        $jwt = new SimpleJWT( [ "uid" => $_SESSION[ 'user' ]->uid ] );
        $jwt->setTimeToLive( 60 );

        CookieManager::setCookie( INIT::$XSRF_TOKEN, $jwt->jsonSerialize(),
                [
                        'expires'  => time() + 60, /* now + 60 seconds */
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => false,
                        'samesite' => 'Strict',
                ]
        );

        $this->response->code( 200 );
    }

}