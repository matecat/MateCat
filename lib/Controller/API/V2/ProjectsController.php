<?php

namespace API\V2;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\ProjectAccessValidator;
use API\Commons\Validators\ProjectPasswordValidator;
use API\V2\Json\Project;
use Constants_JobStatus;
use Exception;
use Exceptions\NotFoundException;
use Jobs_JobDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use ReflectionException;
use Translations_SegmentTranslationDao;
use Utils;

/**
 * This controller can be called as Anonymous, but only if you already know the id and the password
 *
 * Class ProjectsController
 * @package API\V2
 */
class ProjectsController extends KleinController {

    /**
     * @var Projects_ProjectStruct
     */
    private Projects_ProjectStruct $project;

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
     * @throws NotFoundException
     */
    public function setDueDate() {
        $this->updateDueDate();
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function updateDueDate() {
        if (
                array_key_exists( "due_date", $this->params )
                &&
                is_numeric( $this->params[ 'due_date' ] )
                &&
                $this->params[ 'due_date' ] > time()
        ) {

            $due_date    = \Utils::mysqlTimestamp( $this->params[ 'due_date' ] );
            $project_dao = new Projects_ProjectDao;
            $project_dao->updateField( $this->project, "due_date", $due_date );
        }

        $formatted = new Project();

        //$this->response->json( $this->project->toArray() );
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function deleteDueDate() {
        $project_dao = new Projects_ProjectDao;
        $project_dao->updateField( $this->project, "due_date", null );

        $formatted = new Project();
        $this->response->json( [ 'project' => $formatted->renderItem( $this->project ) ] );
    }

    /**
     * @throws Exception
     */
    public function cancel() {
        $this->changeStatus( Constants_JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws Exception
     */
    public function archive() {
        $this->changeStatus( Constants_JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws Exception
     */
    public function active() {
        $this->changeStatus( Constants_JobStatus::STATUS_ACTIVE );
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
                Jobs_JobDao::updateJobStatus( $chunk, $status );

                $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $chunk );
                Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
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