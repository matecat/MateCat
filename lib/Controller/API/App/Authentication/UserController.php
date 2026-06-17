<?php

namespace Controller\API\App\Authentication;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\RateLimiterTrait;
use Exception;
use Klein\Response;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\Authentication\PasswordRules;
use ReflectionException;
use Stomp\Exception\ConnectionException;
use TypeError;

class UserController extends AbstractStatefulKleinController
{

    use RateLimiterTrait;
    use PasswordRules;

    /**
     * @return void
     */
    public function show(): void
    {
        if (empty($_SESSION['user_profile'])) {
            $this->response->code(401);
        }
        $this->response->json($_SESSION['user_profile'] ?? ['error' => 'Invalid login.']);
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

        $old_password = (string) filter_var($this->request->param('old_password'), FILTER_SANITIZE_SPECIAL_CHARS);
        $new_password = (string) filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS);
        $new_password_confirmation = (string) filter_var($this->request->param('password_confirmation'), FILTER_SANITIZE_SPECIAL_CHARS);

        $this->validatePasswordRequirements($new_password, $new_password_confirmation);

        $cpModel = $this->createChangePasswordModel();
        $cpModel->changePassword($old_password, $new_password);

        $this->broadcastLogout();

        $this->response->code(200);
    }

    /**
     * @return void
     */
    public function redeemProject(): void
    {
        $_SESSION['redeem_project'] = true;
        $this->response->code(200);
    }

    protected function createChangePasswordModel(): ChangePasswordModel
    {
        return new ChangePasswordModel($this->user);
    }

    protected function registerValidators(): void
    {
        $loginValidator = new LoginValidator($this);
        $this->appendValidator($loginValidator);
    }

}
