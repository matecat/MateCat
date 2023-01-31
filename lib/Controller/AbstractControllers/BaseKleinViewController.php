<?php

use AbstractControllers\IController;
use API\App\AbstractStatefulKleinController;

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

    protected function setDefaultTemplateData() {
        $this->view->footer_js      = array();
        $this->view->config_js      = array() ;
        $this->view->css_resources  = array();

        $this->view->isLoggedIn    = $this->isLoggedIn();
        $this->view->logged_user   = ( $this->isLoggedIn() ) ? $this->user->shortName() : "";
        $this->view->extended_user = ( $this->isLoggedIn() ) ? $this->user->fullName() : "";
        $this->view->userMail      = $this->user->email;
        $oauth_client = OauthClient::getInstance()->getClient();
        $this->view->authURL = $oauth_client->createAuthUrl();
        $this->view->gdriveAuthURL = \ConnectedServices\GDrive::generateGDriveAuthUrl();
        $this->view->dqf_enabled = false ;
        $this->view->googleDriveEnabled = Bootstrap::isGDriveConfigured() ;

    }

    public function setView( $template_name ) {
        $this->view = new \PHPTALWithAppend( $template_name );

    }

    private function isLoggedIn() {
        return !is_null( $this->getUser() );
    }
}