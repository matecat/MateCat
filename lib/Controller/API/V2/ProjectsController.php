<?php

namespace API\V2  ;

use API\V2\Json\Project;
use API\V2\Json\ProjectAnonymous;
use API\V2\Validators\ProjectPasswordValidator;

/**
 * This controller can be called as Anonymous, but only if you already know the id and the password
 *
 * Class ProjectsController
 * @package API\V2
 */
class ProjectsController extends KleinController {

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    /**
     * @var ProjectPasswordValidator
     */
    private $projectValidator;

    public function get(){

        $this->project = $this->projectValidator->getProject();

        if ( empty( $this->user ) ) {
            $formatted = new ProjectAnonymous();
        } else {
            $formatted = new Project();
        }

        $this->response->json( array( 'project' => $formatted->renderItem( $this->project ) ) );

    }

    protected function afterConstruct() {
        $this->projectValidator = new ProjectPasswordValidator( $this );
        $this->appendValidator( $this->projectValidator );
    }

}