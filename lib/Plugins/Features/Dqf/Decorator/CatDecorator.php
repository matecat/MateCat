<?php

namespace Features\Dqf\Decorator ;

use AbstractDecorator;
use catController;
use Features\Dqf\Model\CatAuthorizationModel;
use INIT;
use Routes;

class CatDecorator extends AbstractDecorator {

    /**
     * @var catController
     */
    protected $controller;

    /**
     * @var \PHPTALWithAppend
     */
    protected $template ;

    public function decorate() {
        $controller = $this->controller ; // done for PHP7 warning

        $authorizationModel = new CatAuthorizationModel(
                $this->controller->getJob(), $controller::isRevision()
        );

        if ( INIT::$DQF_ENABLED ) {
            $this->template->append('footer_js', Routes::appRoot() . 'public/js/dqf-cat.js') ;
            $this->template->dqf_user_status = $authorizationModel->getStatusWithImplicitAssignment( $controller->getLoggedUser() ) ;
        }

    }
}