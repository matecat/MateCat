<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Exceptions\RenderTerminatedException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use InvalidArgumentException;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Model\ConnectedServices\Oauth\OauthClient;
use Model\ConnectedServices\Oauth\ProviderUser;
use Model\Teams\TeamDao;
use Model\Users\Authentication\OAuthSignInModel;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use ReflectionException;
use TypeError;
use Utils\Registry\AppConfig;

class OauthResponseHandlerController extends BaseKleinViewController
{

    /**
     * @var ProviderUser
     */
    private ProviderUser $remoteUser;

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     * @throws RenderTerminatedException
     * @throws ResponseAlreadySentException
     * @throws TypeError
     */
    public function renderView(): void
    {
        $params = filter_var_array($this->request->params(), [
            'provider' => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS],
            'state' => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS],
            'code' => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS],
            'error' => ['filter' => FILTER_SANITIZE_SPECIAL_CHARS]
        ]);

        if (empty($params['state']) || $_SESSION[$params['provider'] . '-' . AppConfig::$XSRF_TOKEN] !== $params['state']) {
            $this->render(401);
        }

        if (!empty($params['code'])) {
            $provider = is_string($params['provider']) ? $params['provider'] : null;
            $this->_processSuccessfulOAuth($params['code'], $provider);
        }

        $this->render(200);
    }

    /**
     * @throws Exception
     */
    protected function initDependencies(): void
    {
        $this->setView('oauth_response_handler.html', ['wanted_url' => $_SESSION['wanted_url'] ?? null]); //https://dev.matecat.com/translate/205-txt/en-GB-it-IT/25-8a4ee829fb52
    }

    /**
     * Successful OAuth2 authentication handling
     *
     * @param string   $code
     * @param string|null $provider
     *
     * @throws Exception
     * @throws EnvironmentIsBrokenException
     * @throws ReflectionException
     * @throws RenderTerminatedException
     * @throws TypeError
     */
    protected function _processSuccessfulOAuth(string $code, ?string $provider = null): void
    {
        // OAuth2 authentication
        $this->_initRemoteUser($code, $provider);

        $model = new OAuthSignInModel(
            $_SESSION,
            $this->remoteUser->email,
            $this->remoteUser->name,
            $this->remoteUser->lastName,
            new UserDao($this->getDatabase()),
            new MetadataDao($this->getDatabase()),
            new TeamDao($this->getDatabase())
        );

        $model->setProvider($this->remoteUser->provider);
        $model->setProfilePicture($this->remoteUser->picture);
        $model->setAccessToken($this->remoteUser->authToken);

        $model->signIn();
    }

    /**
     * This method fetches the remote user
     * from the OAuth2 provider
     *
     * @param string      $code
     * @param string|null $provider
     *
     * @throws InvalidArgumentException
     * @throws RenderTerminatedException
     * @throws ResponseAlreadySentException
     * @throws LockedResponseException
     * @throws TypeError
     */
    protected function _initRemoteUser(string $code, ?string $provider = null): void
    {
        try {
            $client = OauthClient::getInstance($provider)->getProvider();
            $token = $client->getAccessTokenFromAuthCode($code);
            $this->remoteUser = $client->getResourceOwner($token);
        } catch (Exception $exception) {
            $this->render($exception->getCode() >= 400 && $exception->getCode() < 500 ? $exception->getCode() : 400);
        }
    }

}
