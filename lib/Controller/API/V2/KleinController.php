<?php

namespace API\V2;

abstract class KleinController {

    /**
     * @var \Klein\Request
     */
    protected $request;

    /**
     * @var \Klein\Response
     */
    protected $response;
    protected $service;
    protected $app;

    protected $downloadToken;

    protected $api_key;
    protected $api_secret;
    protected $api_record;

    public function __construct( $request, $response, $service, $app ) {
        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
        $this->app      = $app;

        $this->afterConstruct();
    }

    public function respond( $method ) {
        $this->validateAuth();

        if ( !$this->response->isLocked() ) {
            $this->$method();
        }
    }

    protected function validateAuth() {
        $headers = $this->request->headers();

        $this->api_key    = $headers[ 'x-matecat-key' ];
        $this->api_secret = $headers[ 'x-matecat-secret' ];

        if ( !$this->validKeys() ) {
            throw new AuthenticationError();
        }

        $this->validateRequest();
    }

    protected function validKeys() {
        if ( $this->api_key && $this->api_secret ) {
            $this->api_record = \ApiKeys_ApiKeyDao::findByKey( $this->api_key );

            return $this->api_record &&
                    $this->api_record->validSecret( $this->api_secret );
        } else {
            // TODO: Check a cookie to know if the request is coming from
            // MateCat itself.
        }

        return true;
    }

    protected function validateRequest() {
        throw new \Exception( 'to be implemented' );
    }

    /**
     *
     * @param null $tokenContent
     */
    protected function unlockDownloadToken( $tokenContent = null ) {
        if ( !isset( $this->downloadToken ) || empty( $this->downloadToken ) ) {
            return;
        }

        if ( empty( $tokenContent ) ) {
            $cookieContent = json_encode( array(
                    "code"    => 0,
                    "message" => "Download complete."
            ) );
        } else {
            $cookieContent = $tokenContent;
        }

        setcookie(
                $this->downloadToken,
                $cookieContent,
                2147483647,            // expires January 1, 2038
                "/",
                $_SERVER[ 'HTTP_HOST' ]
        );

        $this->downloadToken = null;
    }

    protected function afterConstruct() {
    }

}
