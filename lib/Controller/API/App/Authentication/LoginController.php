<?php
/**
 * Created by PhpStorm.
 * User: Domenico <ostico@gmail.com>, <domenico@translated.net>
 * Date: 19/09/2024
 * Time: 09:38
 */

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\Traits\RateLimiterTrait;
use Exception;
use Klein\Response;
use Model\Teams\TeamDao;
use Model\Users\RedeemableProject;
use Model\Users\UserDao;
use ReflectionException;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;
use Utils\Tools\Utils;

class LoginController extends AbstractStatefulKleinController
{

    use RateLimiterTrait;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function directLogout(): void
    {
        $this->logout();
        $this->response->code(200);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function login(): void
    {
        $params = filter_var_array($this->request->params(), [
            'email' => FILTER_SANITIZE_EMAIL,
            // Compared against a stored hash byte for byte, so it has to arrive exactly as typed. This
            // must stay in step with how the signup and reset paths store it.
            'password' => FILTER_UNSAFE_RAW
        ]);

        $emailIdentifier = is_string($params['email']) && $params['email'] !== '' ? $params['email'] : 'BLANK_EMAIL';

        $rateLimitEmailResponse = $this->checkAndIncrementRateLimit($this->response, $emailIdentifier, '/api/app/user/login', 5);
        $rateLimitIpResponse = $this->checkAndIncrementRateLimit($this->response, Utils::getRealIpAddr() ?? "127.0.0.1", '/api/app/user/login', 5);

        if ($rateLimitEmailResponse instanceof Response) {
            $this->response = $rateLimitEmailResponse;

            return;
        }

        if ($rateLimitIpResponse instanceof Response) {
            $this->response = $rateLimitIpResponse;

            return;
        }

        // XSRF-Token (Signed Double-Submit): verify signature + expiry, then bind to the session
        $xsrfToken = $this->request->headers()->get(AppConfig::$XSRF_TOKEN);

        if (!is_string($xsrfToken)) {
            $this->response->code(403);

            return;
        }

        try {
            $jwt = SimpleJWT::getValidatedInstanceFromString($xsrfToken, AppConfig::$AUTHSECRET);
        } catch (Exception) {
            $this->response->code(403);

            return;
        }

        // single-use: the token must match the csrf issued to THIS browser session (CWE-352)
        $sessionCsrf = $this->sessionStore()->get('login_csrf');
        $this->sessionStore()->remove('login_csrf');

        $tokenCsrf = $jwt['csrf'];
        if (!is_string($sessionCsrf) || !is_string($tokenCsrf) || !hash_equals($sessionCsrf, $tokenCsrf)) {
            $this->response->code(403);

            return;
        }

        $dao = $this->createUserDao();
        $user = is_string($params['email']) ? $dao->getByEmail($params['email']) : null;

        if ($user && is_string($params['password']) && $user->passwordMatch($params['password']) && !is_null($user->email_confirmed_at)) {
            // The password has just been verified, so this is one of the only two moments the
            // plaintext is in hand. Accounts stored with an empty salt get one now, silently — the
            // updateUser() below writes every column, so it costs no extra query.
            $user->rotateEmptySalt($params['password']);

            $user->clearAuthToken();

            $dao->updateUser($user);
            $uid = $user->uid ?? throw new Exception('User not authenticated');
            $dao->destroyCacheByUid($uid);

            $project = new RedeemableProject(
                $user,
                $this->sessionStore(),
                new TeamDao($this->getDatabase())
            );
            $project->tryToRedeem();

            (new AuthCookie(new SessionTokenStoreHandler()))->setCredentials($user);
            AuthenticationHelper::fromRequest($this->sessionStore(), $this->getDatabase());

            $this->response->code(200);
        } else {
            $this->response->code(404);
        }
    }

    protected function createUserDao(): UserDao
    {
        return new UserDao($this->getDatabase());
    }

    /**
     * Signed Double-Submit Cookie
     * @throws Exception
     * @throws TypeError
     */
    public function token(): void
    {
        $csrf = Utils::uuid4();
        $this->sessionStore()->set('login_csrf', $csrf);

        $jwt = new SimpleJWT(
            [
                "csrf" => $csrf
            ],
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            60
        );
        $this->response->header(AppConfig::$XSRF_TOKEN, $jwt->jsonSerialize());
        $this->response->code(200);
    }

    /**
     * Signed Double-Submit Cookie
     * @throws Exception
     * @throws TypeError
     */
    public function socketToken(): void
    {
        // Two conditions, and both are load-bearing.
        //
        // isLoggedIn() is the ring-proven one: it is false unless this request presented a cookie
        // whose token is still live in active_user_login_tokens:<uid>. Without it this route minted
        // an identity from the session alone, and authenticate() does not clear session['uid'] when
        // the ring rejects — only destroyAuthentication() does. So a user whose tokens had been
        // revoked elsewhere (logout on another device, password change, password reset) went on
        // being handed freshly signed socket credentials for their own uid until the session died
        // of idleness, which for anyone still making requests is never. This route carries no
        // LoginValidator, so nothing else was checking.
        //
        // The session uid is still required, so the set of requests answered 406 does not grow: an
        // api-key caller passes isLoggedIn() but never reaches setUserSession(), so it holds no
        // session uid and is refused exactly as before. This route is the UI's, not the stateless
        // API's.
        //
        // The minted uid is read from the ring-proven identity rather than from the session, so the
        // token cannot name an account the ring did not just authenticate on this request.
        $uid = $this->isLoggedIn() ? $this->user->uid : null;

        if ($uid === null || $this->sessionStore()->get('uid') === null) {
            $this->response->code(406);

            return;
        }

        $jwt = new SimpleJWT(
            [
                "uid" => $uid
            ],
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            60
        );

        $this->response->header(AppConfig::$XSRF_TOKEN, $jwt->jsonSerialize());
        $this->response->code(200);
    }

}
