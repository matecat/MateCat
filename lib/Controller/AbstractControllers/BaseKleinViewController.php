<?php

use AbstractControllers\IController;
use API\Commons\AbstractStatefulKleinController;

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
    protected $view;

    public function setView( $template_name ) {
        $this->view = new PHPTALWithAppend( $template_name );

    }

    private function isLoggedIn() {
        return !is_null( $this->getUser() );
    }
}