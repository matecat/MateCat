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
use Utils\Registry\AppConfig;
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

    private static array $providers = [
            GoogleProvider::PROVIDER_NAME    => GoogleProvider::class,
            GithubProvider::PROVIDER_NAME    => GithubProvider::class,
            LinkedInProvider::PROVIDER_NAME  => LinkedInProvider::class,
            MicrosoftProvider::PROVIDER_NAME => MicrosoftProvider::class,
            FacebookProvider::PROVIDER_NAME  => FacebookProvider::class,
    ];

    /**
     * @param string|null $provider
     * @param string|null $redirectUrl
     *
     * @return OauthClient
     */
    public static function getInstance(?string $provider = null, ?string $redirectUrl = null): OauthClient
    {
        if (self::$instance == null or self::$instance->provider_name != $provider) {
            self::$instance = new OauthClient($provider, $redirectUrl);
        }

        self::$instance->provider_name = $provider;

        return self::$instance;
    }

    /**
     * OauthClient constructor.
     *
     * @param string|null $provider
     * @param string|null $redirectUrl
     */
    private function __construct(?string $provider = null, ?string $redirectUrl = null)
    {
        $className      = self::$providers[ $provider ] ?? GoogleProvider::class;
        $this->provider = new $className($redirectUrl);
    }

    /**
     * @return AbstractProvider
     */
    public function getProvider(): AbstractProvider
    {
        return $this->provider;
    }

    /**
     * @param string|null $suffixKey
     * @param array|null  $_session
     *
     * @return string
     * @throws Exception
     */
    public function getAuthorizationUrl(?array &$_session = [], ?string $suffixKey = ''): string
    {
        $session =& $_session;
        if (!isset($session[ $this->provider::PROVIDER_NAME . $suffixKey . '-' . AppConfig::$XSRF_TOKEN ])) {
            $session[ $this->provider::PROVIDER_NAME . $suffixKey . '-' . AppConfig::$XSRF_TOKEN ] = Utils::uuid4();
        }

        return $this->provider->getAuthorizationUrl($session[ $this->provider::PROVIDER_NAME . $suffixKey . '-' . AppConfig::$XSRF_TOKEN ]);
    }

}
