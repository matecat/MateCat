<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/09/24
 * Time: 11:58
 *
 */

namespace ConnectedServices;

use ConnectedServices\Facebook\FacebookClient;
use ConnectedServices\Github\GithubClient;
use ConnectedServices\Google\GoogleClient;
use ConnectedServices\LinkedIn\LinkedInClient;
use ConnectedServices\Microsoft\MicrosoftClient;
use Exception;
use INIT;
use Utils;

class OauthClient {

    /**
     * @var self|null
     */
    private static ?OauthClient $instance = null;

    /**
     * @var string
     */
    private string $provider = 'Mock';

    /**
     * @var AbstractClient
     */
    private AbstractClient $client;

    private static array $providers = [
            GoogleClient::PROVIDER_NAME    => GoogleClient::class,
            GithubClient::PROVIDER_NAME    => GithubClient::class,
            LinkedInClient::PROVIDER_NAME  => LinkedInClient::class,
            MicrosoftClient::PROVIDER_NAME => MicrosoftClient::class,
            FacebookClient::PROVIDER_NAME  => FacebookClient::class,
    ];

    /**
     * @param string|null $provider
     * @param string|null $redirectUrl
     *
     * @return OauthClient
     */
    public static function getInstance( ?string $provider = null, ?string $redirectUrl = null ): OauthClient {
        if ( self::$instance == null or self::$instance->provider != $provider ) {
            self::$instance = new OauthClient( $provider, $redirectUrl );
        }

        self::$instance->provider = $provider;

        return self::$instance;
    }

    /**
     * OauthClient constructor.
     *
     * @param string|null $provider
     * @param string|null $redirectUrl
     */
    private function __construct( ?string $provider = null, ?string $redirectUrl = null ) {
        $className = self::$providers[ $provider ] ?? GoogleClient::class;
        $this->client = new $className( $redirectUrl );
    }

    /**
     * @return AbstractClient
     */
    public function getClient(): AbstractClient {
        return $this->client;
    }

    /**
     * @param string|null $suffixKey
     * @param array|null  $_session
     *
     * @return string
     * @throws Exception
     */
    public function getAuthorizationUrl( ?array &$_session = [], ?string $suffixKey = '' ): string {
        $session =& $_session;
        if ( !isset( $session[ $this->client::PROVIDER_NAME . $suffixKey . '-' . INIT::$XSRF_TOKEN ] ) ) {
            $session[ $this->client::PROVIDER_NAME . $suffixKey . '-' . INIT::$XSRF_TOKEN ] = Utils::uuid4();
        }

        return $this->client->getAuthorizationUrl( $session[ $this->client::PROVIDER_NAME . $suffixKey . '-' . INIT::$XSRF_TOKEN ] );
    }

}
