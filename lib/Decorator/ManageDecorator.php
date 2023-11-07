<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/04/2018
 * Time: 17:19
 */

class ManageDecorator {

    private $controller;

    /**
     * @var PHPTALWithAppend
     */
    private $template;

    public function __construct( manageController $controller, PHPTAL $template ) {

        $this->controller = $controller;
        $this->template   = $template;

    }

    public function decorate() {

        $this->template->logged_user   = ( $this->controller->isLoggedIn() !== false ) ? $this->controller->getUser()->shortName() : "";
        $this->template->build_number  = INIT::$BUILD_NUMBER;
        $this->template->basepath      = INIT::$BASEURL;
        $this->template->hostpath      = INIT::$HTTPHOST;
        $this->template->v_analysis    = var_export( INIT::$VOLUME_ANALYSIS_ENABLED, true );
        $this->template->enable_omegat = ( INIT::$ENABLE_OMEGAT_DOWNLOAD !== false );
        $this->template->globalMessage = Utils::getGlobalMessage()[ 'messages' ];

        $this->template->split_enabled    = true;
        $this->template->enable_outsource = true;

    }

}
