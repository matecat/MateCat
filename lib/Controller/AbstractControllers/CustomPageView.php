<?php

use API\Commons\BaseKleinViewController;
use Klein\Request;
use Klein\Response;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/05/2025
 * Time: 12.08
 *
 */
class CustomPageView {

    private BaseKleinViewController $handler;

    /**
     * BaseKleinViewController constructor.
     *
     * @throws Exception
     */
    public function __construct() {
        $this->handler = new BaseKleinViewController( Request::createFromGlobals(), new Response(), null, null );
    }

    /**
     * @param       $template_name
     * @param array $params
     * @param int   $code
     *
     * @return void
     */
    public function setView( $template_name, array $params = [], int $code = 200 ) {
        $this->handler->setView( $template_name, $params, $code );
    }

    public function setCode( int $httpCode ) {
        $this->handler->setCode( $httpCode );
    }

    public function renderAndClose( ?int $code = null ) {
        $this->handler->render( $code );
    }

}