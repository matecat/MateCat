<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\Abstracts\Authentication\UserProfileBuilder;
use Controller\Abstracts\Authentication\UserStateStore;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\RateLimiterTrait;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Klein\Response;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\Authentication\PasswordRules;
use Model\Users\UserDao;
use ReflectionException;
use RuntimeException;
use Stomp\Exception\ConnectionException;
use TypeError;
use Utils\Redis\RedisHandler;

class UserController extends AbstractStatefulKleinController
{

    use RateLimiterTrait;
    use PasswordRules;

    /**
     * @return void
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     * @throws EnvironmentIsBrokenException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function show(): void
    {
        // Session-scoped on purpose. /api/app/* is the surface the UI calls with a session; every
        // other API is stateless. An api-key request reaches here with an empty session, because
        // the api-key branch of authenticate() sets the user without ever calling setUserSession(),
        // so it still gets a 401.
        //
        // Guarded on `uid` rather than on the api record. Both look like they identify an api-key
        // caller, but they are not the same set: a request presenting a valid key with a *wrong*
        // secret leaves the api record populated and still falls through to the cookie branch, so
        // keying off the record would 401 a session that authenticated perfectly well. `uid` is
        // written by setUserSession() and by nothing else — exactly as session['user'] was before it
        // was deleted for holding the password hash — so the population of callers that get 401 here
        // is unchanged.
        if (!$this->sessionStore()->has('uid')) {
            $this->response->code(401);
            $this->response->json(['error' => 'Invalid login.']);

            return;
        }

        $this->response->json($this->userProfile());
    }

    /**
     * The user-profile payload, read from the uid-keyed store and built on a miss.
     *
     * This is the only place the profile is built. It used to be built by setUserSession() on every
     * request that missed the session cache, which put a fan-out of one query per member per team
     * on the authenticated path for requests that never looked at the result.
     *
     * @return array<string, mixed>
     *
     * @throws EnvironmentIsBrokenException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    private function userProfile(): array
    {
        $uid = $this->user->getUid() ?? throw new RuntimeException('Authenticated user has no uid');
        $store = new UserStateStore();

        $cached = $store->getProfile($uid);

        if ($cached !== null) {
            return $cached;
        }

        // Measured and handed to the store so the XFetch envelope carries the real cost of this
        // build rather than the trait's 0.05s fallback.
        $startedAt = microtime(true);
        $profile = UserProfileBuilder::fromDatabase(
            $this->getDatabase(),
            (new RedisHandler())->getConnection()
        )->build($this->user);

        $store->setProfile($uid, $profile, microtime(true) - $startedAt);

        return $profile;
    }

    /**
     * Changes the password of a logged-in user.
     *
     * This method first checks if the rate limit for changing password has been reached. If the limit has been
     * reached, the method returns without performing any password change.
     *
     * The old password, new password, and password confirmation are retrieved from the request parameters and
     * then sanitized using FILTER_SANITIZE_SPECIAL_CHARS. The sanitized values are then passed to the `changePassword()`
     * method of the `ChangePasswordModel` object.
     *
     * After changing the password, it increments the rate limit counter for the user's email
     * * and sets the response code to 200.
     *
     * The HTTP response code is set to 200 upon successful password change.
     *
     * @return void
     * @throws ValidationError
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    public function changePasswordAsLoggedUser(): void
    {
        $emailIdentifier = $this->user->email ?? 'BLANK_EMAIL';
        $checkRateLimitEmail = $this->checkAndIncrementRateLimit($this->response, $emailIdentifier, '/api/app/user/password/change', 5);
        if ($checkRateLimitEmail instanceof Response) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        $this->rejectControlCharacters($this->request->param('password'));
        $this->rejectControlCharacters($this->request->param('password_confirmation'));
        // All three unescaped on purpose: the old one is verified against the stored hash and the new
        // one replaces it, so both must be exactly what the user typed.
        $old_password = (string)$this->request->param('old_password');
        $new_password = (string)$this->request->param('password');
        $new_password_confirmation = (string)$this->request->param('password_confirmation');

        $this->validatePasswordRequirements($new_password, $new_password_confirmation);

        $cpModel = $this->createChangePasswordModel();
        $cpModel->changePassword($old_password, $new_password);

        $this->broadcastLogout();

        $this->response->code(200);
    }

    /**
     * @return void
     * @throws LockedResponseException
     */
    public function redeemProject(): void
    {
        $this->sessionStore()->set('redeem_project', true);
        $this->response->code(200);
    }

    protected function createChangePasswordModel(): ChangePasswordModel
    {
        return new ChangePasswordModel($this->user, new UserDao($this->getDatabase()), new SessionTokenStoreHandler());
    }

    protected function registerValidators(): void
    {
        $loginValidator = new LoginValidator($this);
        $this->appendValidator($loginValidator);
    }

}
