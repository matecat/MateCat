<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;
use Utils;
use Utils\Constants\JobStatus;
use View\API\V2\Json\Project;

/**
 * This controller can be called as Anonymous, but only if you already know the id and the password
 *
 * Class ProjectsController
 * @package API\V2
 */
class ProjectsController extends KleinController {

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;

    /**
     * @return void
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    public function get() {

        $formatted = new Project();
        $formatted->setUser( $this->user );
        if ( !empty( $this->api_key ) ) {
            $formatted->setCalledFromApi( true );
        }

        $this->featureSet->loadForProject( $this->project );
        $projectOutputFields = $formatted->renderItem( $this->project );
        $this->response->json( [ 'project' => $projectOutputFields ] );

    }

    /**
     * @throws ReflectionException
     */
    public function setDueDate() {
        $this->updateDueDate();
    }

    /**
     * @throws ReflectionException
     */
    public function updateDueDate() {
        if (
                array_key_exists( "due_date", $this->params )
                &&
                is_numeric( $this->params[ 'due_date' ] )
                &&
                $this->params[ 'due_date' ] > time()
        ) {

            $due_date    = Utils::mysqlTimestamp( $this->params[ 'due_date' ] );
            $project_dao = new ProjectDao;
            $project_dao->updateField( $this->project, "due_date", $due_date );
        }

        $formatted = new Project();

        //$this->response->json( $this->project->toArray() );
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    /**
     * @throws ReflectionException
     */
    public function deleteDueDate() {
        $project_dao = new ProjectDao;
        $project_dao->updateField( $this->project, "due_date", null );

        $formatted = new Project();
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    /**
     * @throws Exception
     */
    public function cancel() {
        $this->changeStatus( JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws Exception
     */
    public function archive() {
        $this->changeStatus( JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws Exception
     */
    public function active() {
        $this->changeStatus( JobStatus::STATUS_ACTIVE );
    }

    /**
     * @throws Exception
     */
    protected function changeStatus( $status ) {

        ( new ProjectAccessValidator( $this, $this->project ) )->validate();

        $chunks = $this->project->getJobs();

        foreach ( $chunks as $chunk ) {

            // update a job only if it is NOT deleted
            if ( !$chunk->isDeleted() ) {
                JobDao::updateJobStatus( $chunk, $status );

                $lastSegmentsList = SegmentTranslationDao::getMaxSegmentIdsFromJob( $chunk );
                SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
            }
        }

        $this->response->json( [ 'code' => 1, 'data' => "OK", 'status' => $status ] );

    }

    protected function afterConstruct() {

        $projectValidator = ( new ProjectPasswordValidator( $this ) );

        $projectValidator->onSuccess( function () use ( $projectValidator ) {
            $this->project = $projectValidator->getProject();
        } );

        $this->appendValidator( $projectValidator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}