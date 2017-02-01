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

    /**
     * validKeys
     *
     * This was implemented to allow to pass a pair of keys just to identify the user, not to deny access.
     * This function returns true even if keys are not provided.
     *
     * If keys are provided, it checks for them to be valid.
     *
     */
    protected function validKeys() {
        if ( FALSE !== strpos( $this->api_key, '-' ) ) {
            list( $this->api_key, $this->api_secret ) = explode('-', $this->api_key ) ;
        }

        if ( $this->api_key && $this->api_secret ) {
            $this->api_record = \ApiKeys_ApiKeyDao::findByKey( $this->api_key );

            return $this->api_record &&
                $this->api_record->validSecret( $this->api_secret );
        }

        return true;
    }

    protected function validateRequest() {
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
