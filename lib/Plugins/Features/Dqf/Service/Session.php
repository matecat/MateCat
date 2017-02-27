<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 13:19
 */

namespace Features\Dqf\Service;

use API\V2\Exceptions\AuthenticationError;

class Session {

    protected $client ;
    protected $username ;
    protected $password ;
    protected $sessonId ;
    protected $expires ;

    public function __construct(Client $client, $username, $password ) {
        $this->client = $client ;
        $this->username = $username ;
        $this->password = $password ;
    }

    public function login() {
        $curl = new \MultiCurlHandler();
        $username = $this->client->encrypt( $this->username );
        $password = $this->client->encrypt( $this->password );

        // $username = $this->username ;
        // $password = $this->password ;

        $request = $curl->createResource(
                $this->client->url('/login'),
                $this->client->optionsPost([
                        'email' => $username,
                        'password' => $password,
                    // 'key' => \INIT::$DQF_ENCRYPTION_KEY
                ])
        );

        $curl->multiExec();
        $curl->setRequestHeader( $request );

        $content = json_decode( $curl->getSingleContent( $request ), true );

        if ( $curl->hasError( $request ) ) {
            throw new AuthenticationError('Login failed with message: ' . $content['message'] );
        }

        $response = new LoginResponseStruct( $content['loginResponse'] );

        $this->sessonId = $response->sessionId ;
        $this->expires = $response->expires ;

        return $this;
    }

    public function getSessionId() {
        return $this->sessonId ;
    }

    public function getExpires() {
        return $this->expires ;
    }


}