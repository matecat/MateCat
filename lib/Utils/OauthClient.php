<?php

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\Facebook\FacebookClient;
use ConnectedServices\LinkedIn\LinkedInClient;
use ConnectedServices\Github\GithubClient;
use ConnectedServices\Google\GoogleClient;
use ConnectedServices\Microsoft\MicrosoftClient;

class OauthClient {

    const GITHUB_PROVIDER = 'github';
    const GOOGLE_PROVIDER = 'google';
    const LINKEDIN_PROVIDER = 'linkedin';
    const MICROSOFT_PROVIDER = 'microsoft';
    const FACEBOOK_PROVIDER = 'facebook';

    /**
     * @var self
     */
	private static $instance;

    /**
     * @var string
     */
	private $provider;

    /**
     * @var ConnectedServiceInterface
     */
	private $client;

    /**
     * @param string $provider
     * @return OauthClient
     * @throws Exception
     */
	public static function getInstance($provider = self::GOOGLE_PROVIDER)
    {
		if(self::$instance == null or self::$instance->provider != $provider){
			self::$instance = new OauthClient($provider);
		}

        self::$instance->provider = $provider;

		return self::$instance;
	}

    /**
     * OauthClient constructor.
     * @param string $provider
     */
	private function __construct($provider = null){

        switch ($provider){

            case self::GITHUB_PROVIDER:
                $this->client = new GithubClient();
                break;

            case self::MICROSOFT_PROVIDER:
                $this->client = new MicrosoftClient();
                break;

            case self::LINKEDIN_PROVIDER:
                $this->client = new LinkedInClient();
                break;

            case self::FACEBOOK_PROVIDER:
                $this->client = new FacebookClient();
                break;

            case null:
            case self::GOOGLE_PROVIDER:
            default:
                $this->client = new GoogleClient();
                break;
        }
	}

    /**
     * @return ConnectedServiceInterface
     */
	public function getClient(){
		return $this->client;
	}

}
