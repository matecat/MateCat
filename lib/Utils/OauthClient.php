<?php

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\LinkedIn\LinkedInClient;
use ConnectedServices\Microsoft\GithubClient;
use ConnectedServices\Microsoft\GoogleClient;
use ConnectedServices\Microsoft\MicrosoftClient;

class OauthClient {

    const GITHUB_PROVIDER = 'github';
    const GOOGLE_PROVIDER = 'google';
    const LINKEDIN_PROVIDER = 'linkedin';
    const MICROSOFT_PROVIDER = 'microsoft';

    /**
     * @var self
     */
	private static $instance;

    /**
     * @var ConnectedServiceInterface
     */
	private $client;

    /**
     * @param string $provider
     * @return OauthClient
     * @throws Exception
     */
	public static function getInstance($provider = self::GOOGLE_PROVIDER){
		if(self::$instance == null){
			self::$instance = new OauthClient($provider);
		}
		return self::$instance;
	}

    /**
     * OauthClient constructor.
     * @param string $provider
     */
	private function __construct($provider = self::GOOGLE_PROVIDER){

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

            case self::GOOGLE_PROVIDER:
            default:
                $this->client = new GoogleClient();
                break;
        }

        throw new InvalidArgumentException('Wrong or missing provider');
	}

    /**
     * @return ConnectedServiceInterface
     */
	public function getClient(){
		return $this->client;
	}

}
