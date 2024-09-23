<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/11/2016
 * Time: 09:38
 */

namespace API\App\Authentication;

use AMQHandler;
use API\App\RateLimiterTrait;
use API\Commons\AbstractStatefulKleinController;
use API\Commons\Authentication\AuthCookie;
use API\Commons\Authentication\AuthenticationHelper;
use CookieManager;
use Exception;
use INIT;
use Klein\Response;
use SimpleJWT;
use Stomp\Transport\Message;
use Users\RedeemableProject;
use Users_UserDao;
use Utils;

class LoginController extends AbstractStatefulKleinController {

    use RateLimiterTrait;

    public function logout() {
        $uid = $_SESSION[ 'uid' ];
        unset( $_SESSION );
        AuthCookie::destroyAuthentication();
        $queueHandler = new AMQHandler();
        $message      = json_encode( [
                '_type' => 'logout',
                'data'  => [
                        'uid' => $uid
                ]
        ] );
        $queueHandler->publishToTopic( INIT::$SSE_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );
        $this->response->code( 200 );
    }

    /**
     * @throws Exception
     */
    public function login() {

        // XSRF-Token
        $xsrfToken = $this->request->headers()->get( INIT::$XSRF_TOKEN );

        if ( $xsrfToken === null ) {
            $this->response->code( 403 );

            return;
        }

        try {
            SimpleJWT::getValidPayload( $xsrfToken );
        } catch ( Exception $exception ) {
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

        $params = filter_var_array( $this->request->params(), [
                'email'    => FILTER_SANITIZE_EMAIL,
                'password' => FILTER_SANITIZE_STRING
        ] );

        $checkRateLimitResponse = $this->checkRateLimitResponse( $this->response, $params[ 'email' ], '/api/app/user/login' );
        if ( $checkRateLimitResponse instanceof Response ) {
            $this->response = $checkRateLimitResponse;

            return;
        }

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

}