<?php

namespace Features\Dqf\Decorator ;

use AbstractDecorator;
use catController;
use Features\Dqf;
use Features\Dqf\Model\CatAuthorizationModel;
use Features\Dqf\Utils\Functions;
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

        Functions::commonVarsForDecorator( $this->template );

        $project = $this->controller->getChunk()->getProject() ;

        if ( $project->isFeatureEnabled( Dqf::FEATURE_CODE ) ) {
            $this->template->append('footer_js', Routes::appRoot() . 'public/js/dqf-cat.js') ;

            $authorizationModel = new CatAuthorizationModel( $this->controller->getChunk(), $controller::isRevision() );
            $this->template->dqf_user_status   = $authorizationModel->getStatus( $controller->getUser() ) ;

            $metadataKeyValue = $project->getMetadataAsKeyValue() ;
            $this->template->dqf_selected_content_types = $metadataKeyValue['dqf_content_type'] ;
            $this->template->dqf_selected_industry      = $metadataKeyValue['dqf_industry'] ;
            $this->template->dqf_selected_quality_level = $metadataKeyValue['dqf_quality_level'] ;
            $this->template->dqf_selected_process       = $metadataKeyValue['dqf_process'] ;
            $this->template->dqf_active_on_project      = true ;
        }
    }
}