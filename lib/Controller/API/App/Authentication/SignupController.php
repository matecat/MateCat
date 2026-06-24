<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\Abstracts\FlashMessage;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Traits\RateLimiterTrait;
use Controller\Views\CustomPageView;
use Exception;
use InvalidArgumentException;
use Klein\Exceptions\ResponseAlreadySentException;
use Klein\Response;
use Model\Teams\InvitedUser;
use Model\Teams\TeamDao;
use Model\Users\Authentication\PasswordRules;
use Model\Users\Authentication\SignupModel;
use Model\Users\RedeemableProject;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;

class SignupController extends AbstractStatefulKleinController
{

    use RateLimiterTrait;
    use PasswordRules;

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function create(): void
    {
        $user = $this->validateCreationRequest();

        $userIp = Utils::getRealIpAddr() ?? '127.0.0.1';

        // rate limit on email
        $checkRateLimitOnEmail = $this->checkAndIncrementRateLimit($this->response, $user['email'], '/api/app/user', 3);
        if ($checkRateLimitOnEmail instanceof Response) {
            $this->response = $checkRateLimitOnEmail;

            return;
        }

        // rate limit on IP
        $checkRateLimitOnIp = $this->checkAndIncrementRateLimit($this->response, $userIp, '/api/app/user', 3);
        if ($checkRateLimitOnIp instanceof Response) {
            $this->response = $checkRateLimitOnIp;

            return;
        }

        $signup = $this->createSignupModel($user, $_SESSION);
        $signup->processSignup();
        $this->response->code(200);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function authenticateConfirmedUser(UserStruct $user): void
    {
        AuthCookie::setCredentials($user, new SessionTokenStoreHandler());
        AuthenticationHelper::fromRequest($_SESSION, $this->getDatabase());
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function createInvitedUser(): InvitedUser
    {
        return new InvitedUser('', null, new TeamDao($this->getDatabase()), null, new UserDao($this->getDatabase()));
    }

    /**
     * @param UserStruct $user
     * @param array<string, mixed> $session
     *
     * @return RedeemableProject
     */
    protected function createRedeemableProject(UserStruct $user, array &$session): RedeemableProject
    {
        return new RedeemableProject($user, $session, new TeamDao($this->getDatabase()));
    }

    /**
     * @throws Exception
     * @throws RenderTerminatedException
     * @throws InvalidArgumentException
     * @throws ResponseAlreadySentException
     * @throws TypeError
     */
    protected function renderErrorPage(): void
    {
        $controllerInstance = new CustomPageView();
        $controllerInstance->setView('410.html', [], 410);
        $controllerInstance->render();
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $session
     *
     * @return SignupModel
     */
    protected function createSignupModel(array $params, array &$session): SignupModel
    {
        return new SignupModel($params, $session, new UserDao($this->getDatabase()), new TeamDao($this->getDatabase()));
    }

    /**
     * @return array<string, mixed>
     * @throws ValidationError
     */
    private function validateCreationRequest(): array
    {
        $user = filter_var_array(
            (array)$this->request->param('user'),
            [
                'email' => ['filter' => FILTER_SANITIZE_EMAIL, 'options' => []],
                'password' => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'options' => FILTER_FLAG_STRIP_LOW],
                'password_confirmation' => [
                    'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                    'options' => FILTER_FLAG_STRIP_LOW
                ],
                'first_name' => [
                    'filter' => FILTER_CALLBACK,
                    'options' => function ($firstName) {
                        return CatUtils::stripMaliciousContentFromAName($firstName);
                    }
                ],
                'last_name' => [
                    'filter' => FILTER_CALLBACK,
                    'options' => function ($lastName) {
                        return CatUtils::stripMaliciousContentFromAName($lastName);
                    }
                ],
                'wanted_url' => [
                    'filter' => FILTER_CALLBACK,
                    'options' => function ($wanted_url) {
                        $wanted_url = filter_var($wanted_url, FILTER_SANITIZE_URL);
                        if ($wanted_url === false) {
                            return AppConfig::$HTTPHOST;
                        }
                        $parsed = parse_url($wanted_url);
                        $parsedHost = parse_url(AppConfig::$HTTPHOST);
                        if ($parsed === false || $parsedHost === false) {
                            return AppConfig::$HTTPHOST;
                        }
                        return ($parsed['host'] ?? '') !== ($parsedHost['host'] ?? '') ? AppConfig::$HTTPHOST : $wanted_url;
                    }
                ]
            ]
        );

        if (empty($user['email'])) {
            throw new ValidationError('Missing email');
        }

        if (empty($user['first_name'])) {
            throw new ValidationError("First name must contain at least one letter");
        }

        if (empty($user['last_name'])) {
            throw new ValidationError("Last name must contain at least one letter");
        }

        $this->validatePasswordRequirements(
            is_string($user['password']) ? $user['password'] : '',
            is_string($user['password_confirmation']) ? $user['password_confirmation'] : ''
        );

        return $user;
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function confirm(): void
    {
        $token = filter_var(
            $this->request->param('token'),
            FILTER_SANITIZE_SPECIAL_CHARS,
            FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
        );

        $signupModel = $this->createSignupModel(['token' => $token], $_SESSION);

        try {
            $user = $signupModel->confirm();

            $this->authenticateConfirmedUser($user);

            $invitedUser = $this->createInvitedUser();
            if ($invitedUser->hasPendingInvitations()) {
                $invitedUser->completeTeamSignUp($user, $_SESSION['invited_to_team']);
            }

            $project = $this->createRedeemableProject($user, $_SESSION);
            $project->tryToRedeem();

            if ($project->getDestinationURL()) {
                $this->response->redirect($project->getDestinationURL());
            } else {
                $this->response->redirect($signupModel->flushWantedURL());
            }

            FlashMessage::set('popup', 'profile', FlashMessage::SERVICE);
        } catch (Exception $e) {
            FlashMessage::set('confirmToken', $e->getMessage(), FlashMessage::ERROR);

            $this->renderErrorPage();
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    public function resendConfirmationEmail(): void
    {
        $userIp = Utils::getRealIpAddr() ?? '127.0.0.1';
        $emailIdentifier = (string)$this->request->param('email');

        // rate limit on email
        $checkRateLimitOnEmail = $this->checkAndIncrementRateLimit(
            $this->response,
            $emailIdentifier,
            '/api/app/user',
            3
        );
        if ($checkRateLimitOnEmail instanceof Response) {
            $this->response = $checkRateLimitOnEmail;

            return;
        }

        // rate limit on IP
        $checkRateLimitOnIp = $this->checkAndIncrementRateLimit($this->response, $userIp, '/api/app/user', 3);
        if ($checkRateLimitOnIp instanceof Response) {
            $this->response = $checkRateLimitOnIp;

            return;
        }

        SignupModel::resendConfirmationEmail($this->request->param('email'), new UserDao($this->getDatabase()));
        $this->response->code(200);
    }

}
