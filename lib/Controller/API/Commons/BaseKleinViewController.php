<?php

namespace API\Commons;

use AbstractControllers\IController;
use Exception;
use INIT;
use PHPTAL;
use PHPTALWithAppend;
use Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/10/16
 * Time: 10:24
 */
class BaseKleinViewController extends AbstractStatefulKleinController implements IController {

    /**
     * @var PHPTALWithAppend
     */
    protected PHPTAL $view;

    /**
     * @throws Exception
     */
    public function setView( $template_name ) {
        $this->view = new PHPTALWithAppend( $template_name );
        /**
         * This is a unique ID generated at runtime.
         * It is injected into the nonce attribute of `< script >` tags to allow browsers to safely execute the contained CSS and JavaScript.
         */
        $this->view->x_nonce_unique_id          = Utils::uuid4();
        $this->view->x_self_ajax_location_hosts = INIT::$ENABLE_MULTI_DOMAIN_API ? " *.ajax." . parse_url( INIT::$HTTPHOST )[ 'host' ] : null;

    }

}