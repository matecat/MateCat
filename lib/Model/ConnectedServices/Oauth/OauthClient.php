<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/09/24
 * Time: 11:58
 *
 */

namespace Model\ConnectedServices\Oauth;

use Exception;
use Model\ConnectedServices\Oauth\Facebook\FacebookProvider;
use Model\ConnectedServices\Oauth\Github\GithubProvider;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedInProvider;
use Model\ConnectedServices\Oauth\Microsoft\MicrosoftProvider;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Session\SessionStore;
use Utils\Tools\Utils;

class OauthClient
{

    /**
     * @var self|null
     */
    private static ?OauthClient $instance = null;

    /**
     * @var string
     */
    private string $provider_name = 'Mock';

    /**
     * @var AbstractProvider
     */
    private AbstractProvider $provider;

    /** @var array<string, class-string<AbstractProvider>> */
    private static array $providers = [
        GoogleProvider::PROVIDER_NAME => GoogleProvider::class,
        GithubProvider::PROVIDER_NAME => GithubProvider::class,
        LinkedInProvider::PROVIDER_NAME => LinkedInProvider::class,
        MicrosoftProvider::PROVIDER_NAME => MicrosoftProvider::class,
        FacebookProvider::PROVIDER_NAME => FacebookProvider::class,
    ];

    /**
     * @param string|null $provider
     * @param string|null $redirectUrl
     *
     * @return OauthClient
     * @throws TypeError
     */
    public static function getInstance(?string $provider = null, ?string $redirectUrl = null): OauthClient
    {
        if (self::$instance == null or self::$instance->provider_name != ($provider ?? 'Mock')) {
            self::$instance = new OauthClient($provider, $redirectUrl);
        }

        self::$instance->provider_name = $provider ?? 'Mock';

        return self::$instance;
    }

    /**
     * OauthClient constructor.
     *
     * @param string|null $provider
     * @param string|null $redirectUrl
     * @throws TypeError
     */
    private function __construct(?string $provider = null, ?string $redirectUrl = null)
    {
        $className = self::$providers[$provider ?? ''] ?? GoogleProvider::class;
        $instance = new $className($redirectUrl);
        if (!$instance instanceof AbstractProvider) {
            throw new TypeError('Provider class must extend AbstractProvider');
        }
        $this->provider = $instance;
    }

    /**
     * @return AbstractProvider
     */
    public function getProvider(): AbstractProvider
    {
        return $this->provider;
    }

    /**
     * Mint (or reuse) this provider's anti-CSRF `state` and build the authorization url around it.
     *
     * The store is mandatory, which it was not before: the old signature defaulted to
     * `?array &$_session = []`, so a caller that omitted it wrote the token into a throwaway array
     * that died with the call. The url still carried a `state`, but nothing was left anywhere to
     * compare the callback's `state` against — an anti-CSRF token nobody can verify. Requiring the
     * store makes that call unwritable.
     *
     * @throws Exception
     */
    public function getAuthorizationUrl(SessionStore $session, ?string $suffixKey = ''): string
    {
        $stateKey = $this->provider::PROVIDER_NAME . $suffixKey . '-' . AppConfig::$XSRF_TOKEN;

        if (!$session->has($stateKey)) {
            $session->set($stateKey, Utils::uuid4());
        }

        return $this->provider->getAuthorizationUrl($session->get($stateKey));
    }

}
