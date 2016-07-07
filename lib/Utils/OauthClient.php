<?php

/**
 * Class OauthClient.<br/>
 * This class has the responsibility to create a valid Google_Client object
 * according to a specified configuration
 * @see Google_Client
 */
class OauthClient {

	private static $instance;

	private $client;

	public static function getInstance(){
		if(self::$instance == null){
			self::$instance = new OauthClient();
		}
		return self::$instance;
	}

	private function __construct(){
		$this->client = new Google_Client();
		$this->client->setApplicationName(INIT::$OAUTH_CLIENT_APP_NAME);
		$this->client->setClientId(INIT::$OAUTH_CLIENT_ID);
		$this->client->setClientSecret(INIT::$OAUTH_CLIENT_SECRET);
		$this->client->setRedirectUri(INIT::$OAUTH_REDIRECT_URL);
		$this->client->setScopes(INIT::$OAUTH_SCOPES);
	}

	public function getClient(){
		return $this->client;
	}

        /**
         * Set the OAuth Scopes to the array containing Google Drive scopes
         *
         * @return OauthClient instance
         */
        public function setScopesToGDrive() {
                $this->client->setScopes(INIT::$OAUTH_GDRIVE_SCOPES);
                $this->client->setAccessType("offline");
                $this->client->setPrompt("consent");
		return self::$instance;
        }
} 
