<?php

namespace API\V2 ;

use Klein\Klein;

class KleinController {

    /**
     * @var \Klein\Request
     */
    protected $request ;

    /**
     * @var \Klein\Response
     */
    protected $response ;
    protected $service ;
    protected $app ;

    protected $downloadToken ;

    public function __construct( $request, $response, $service, $app) {
        $this->request = $request ;
        $this->response = $response ;
        $this->service = $service ;
        $this->app = $app ;

        $this->afterConstruct();
    }

    public function respond($method) {
        $this->$method() ;
    }

    /**
     *
     * @param null $tokenContent
     */
    protected function unlockDownloadToken( $tokenContent = null ) {
        if ( !isset( $this->downloadToken ) || empty( $this->downloadToken ) ) {
            return ;
        }

        if ( empty( $tokenContent ) ) {
            $cookieContent = json_encode( array(
                "code"    => 0,
                "message" => "Download complete."
            ) ) ;
        }
        else {
            $cookieContent = $tokenContent ;
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

}
