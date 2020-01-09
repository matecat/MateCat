<?php

use ConnectedServices\Google\GoogleClientFactory;

/**
 * Class OauthClient.<br/>
 * This class has the responsibility to create a valid Google_Client object
 * according to a specified configuration
 * @see Google_Client
 */
class OauthClient {

    /**
     * @var self
     */
	private static $instance;

    /**
     * @var Google_Client
     */
	private $client;

    /**
     * @return OauthClient
     */
	public static function getInstance(){
		if(self::$instance == null){
			self::$instance = new OauthClient();
		}
		return self::$instance;
	}

    /**
     * OauthClient constructor.
     */
	private function __construct(){
		$this->client = GoogleClientFactory::create();
	}

    /**
     * @return Google_Client
     */
	public function getClient(){
		return $this->client;
	}

}
