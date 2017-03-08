<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/02/2017
 * Time: 13:19
 */

namespace Features\Dqf\Service;

use API\V2\Exceptions\AuthenticationError;
use Features\Dqf\Service\Struct\LoginRequestStruct;
Use Features\Dqf\Service\Struct\LoginResponseStruct ;

class Session {

    protected $client ;
    protected $email ;
    protected $password ;
    protected $sessonId ;
    protected $expires ;

    public function __construct( Client $client, $email, $password ) {
        $this->client   = $client ;
        $this->email    = $email ;
        $this->password = $password ;
    }

    public function login() {
        $curl = new \MultiCurlHandler();

        $struct = new LoginRequestStruct() ;
        $struct->email = $this->client->encrypt( $this->email );
        $struct->password = $this->client->encrypt( $this->password );

        $this->client->setPostParams( $struct ) ;
        $this->client->setHeaders( $struct );

        $request = $curl->createResource(
                $this->client->url('/login'),
                $this->client->getCurlOptions()
        );

        $curl->setRequestHeader( $request );
        $curl->multiExec();

        $content = json_decode( $curl->getSingleContent( $request ), true );

        if ( $curl->hasError( $request ) ) {
            throw new AuthenticationError('Login failed with message: ' . $curl->getError( $request ) );
        }

        $response = new LoginResponseStruct( $content['loginResponse'] );

        $this->sessonId = $response->sessionId ;
        $this->expires = $response->expires ;

        return $this;
    }

    public function getSessionId() {
        if ( is_null($this->sessonId) ) {
            throw new \Exception('sessionId is null, try to login first');
        }
        return $this->sessonId ;
    }

    public function getExpires() {
        return $this->expires ;
    }


}