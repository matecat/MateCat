<?php

namespace API\V2;

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

    public function get() {

        $this->project = $this->projectValidator->getProject();

        if ( empty( $this->user ) ) {
            $formatted = new ProjectAnonymous();
        } else {
            $formatted = new Project();
        }

        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );

    }

    public function setDueDate() {
        $this->updateDueDate();
    }

    public function updateDueDate() {
        $this->project = $this->projectValidator->getProject();

        if (
                array_key_exists( "due_date", $this->params )
                &&
                is_numeric( $this->params[ 'due_date' ] )
                &&
                $this->params[ 'due_date' ] > time()
        ) {

            $due_date    = \Utils::mysqlTimestamp( $this->params[ 'due_date' ] );
            $project_dao = new \Projects_ProjectDao;
            $project_dao->updateField( $this->project, "due_date", $due_date );
        }
        if ( empty( $this->user ) ) {
            $formatted = new ProjectAnonymous();
        } else {
            $formatted = new Project();
        }

        //$this->response->json( $this->project->toArray() );
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    public function deleteDueDate() {
        $this->project = $this->projectValidator->getProject();

        $project_dao = new \Projects_ProjectDao;
        $project_dao->updateField( $this->project, "due_date", null );

        if ( empty( $this->user ) ) {
            $formatted = new ProjectAnonymous();
        } else {
            $formatted = new Project();
        }
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    protected function afterConstruct() {
        $this->projectValidator = new ProjectPasswordValidator( $this );
        $this->appendValidator( $this->projectValidator );
    }

}