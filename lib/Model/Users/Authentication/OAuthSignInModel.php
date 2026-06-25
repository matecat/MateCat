<?php

namespace Model\Users\Authentication;

use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\Abstracts\FlashMessage;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Teams\TeamDao;
use Model\Users\MetadataDao;
use Model\Users\RedeemableProject;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Email\WelcomeEmail;
use Utils\Tools\Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/02/2018
 * Time: 17:34
 */
class OAuthSignInModel
{

    protected UserStruct $user;
    protected ?string $profilePictureUrl = null;
    protected string $provider;

    /** @var array<string, mixed> */
    protected array $session;

    private UserDao $userDao;
    private MetadataDao $metadataDao;
    private TeamDao $teamDao;

    /**
     * @param array<string, mixed> $session
     * @param UserDao $userDao
     * @param MetadataDao $metadataDao
     * @param TeamDao $teamDao
     */
    public function __construct(
        array &$session,
        string $email,
        ?string $firstName,
        ?string $lastName,
        UserDao $userDao,
        MetadataDao $metadataDao,
        TeamDao $teamDao
    ) {
        if (empty($firstName)) {
            $firstName = "Anonymous";
        }

        if (empty($lastName)) {
            $lastName = "User";
        }

        $this->user = new UserStruct([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email
        ]);

        $this->session     =& $session;
        $this->userDao     = $userDao;
        $this->metadataDao = $metadataDao;
        $this->teamDao     = $teamDao;
    }

    /**
     * @param string $token
     *
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws \TypeError
     */
    public function setAccessToken(string $token): void
    {
        $encoded = json_encode($token) ?: $token;
        $this->user->oauth_access_token = OauthTokenEncryption::getInstance()->encrypt($encoded);
    }

    public function getUser(): UserStruct
    {
        return $this->user;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function signIn(): bool
    {
        $existingUser = $this->user->email !== null ? $this->userDao->getByEmail($this->user->email) : null;

        if ($existingUser) {
            $welcome_new_user = !$existingUser->everSignedIn();
            $this->_updateExistingUser($existingUser);
        } else {
            $welcome_new_user = true;
            $this->_createNewUser();
        }

        if ($welcome_new_user) {
            $this->_welcomeNewUser();
        }

        if (!is_null($this->profilePictureUrl)) {
            $this->_updateProfilePicture();
        }

        $this->_updateProvider();
        $this->_authenticateUser();

        $this->createRedeemableProject()->tryToRedeem();

        return true;
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    protected function _updateProfilePicture(): void
    {
        $uid = $this->user->uid ?? throw new RuntimeException('User uid must be set before updating profile picture');
        $profilePictureUrl = $this->profilePictureUrl ?? throw new RuntimeException('Profile picture url must be set before updating profile picture');

        $this->metadataDao->set($uid, $this->provider . '_picture', $profilePictureUrl);
    }

    public function setProfilePicture(?string $pictureUrl = null): void
    {
        $this->profilePictureUrl = $pictureUrl;
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     */
    protected function _updateProvider(): void
    {
        $uid = $this->user->uid ?? throw new RuntimeException('User uid must be set before updating provider metadata');

        $this->metadataDao->set($uid, 'oauth_provider', $this->provider);
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    protected function _createNewUser(): void
    {
        $this->user->create_date = Utils::mysqlTimestamp(time());
        $this->user->uid = $this->userDao->insertStruct($this->user) ?: throw new RuntimeException('User uid must be set after OAuth insert');

        $this->teamDao->getDatabaseHandler()->begin();
        $this->teamDao->createPersonalTeam($this->user);
        $this->teamDao->getDatabaseHandler()->commit();
    }

    /**
     * @throws Exception
     */
    protected function _updateExistingUser(UserStruct $existing_user): void
    {
        $this->user->uid = $existing_user->uid;
        $this->userDao->updateStruct($this->user, [
            'fields' => ['oauth_access_token']
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function _authenticateUser(): void
    {
        AuthCookie::setCredentials($this->user, new SessionTokenStoreHandler());
        $this->buildAuthHelper();
    }

    protected function buildAuthHelper(): AuthenticationHelper
    {
        return AuthenticationHelper::fromRequest($this->session, $this->teamDao->getDatabaseHandler());
    }

    /**
     * @throws Exception
     */
    protected function _welcomeNewUser(): void
    {
        $this->createWelcomeEmail()->send();
        FlashMessage::set('popup', 'profile', FlashMessage::SERVICE);
    }

    protected function createWelcomeEmail(): WelcomeEmail
    {
        return new WelcomeEmail($this->user);
    }

    protected function createRedeemableProject(): RedeemableProject
    {
        return new RedeemableProject(
            $this->user,
            $this->session,
            $this->teamDao,
            new ProjectDao($this->teamDao->getDatabaseHandler()),
            new JobDao($this->teamDao->getDatabaseHandler())
        );
    }

}
