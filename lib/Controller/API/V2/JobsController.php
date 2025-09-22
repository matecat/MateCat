<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:39
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationDao;
use Utils\Constants\JobStatus;
use Utils\Tools\Utils;
use View\API\V2\Json\Chunk;

class JobsController extends KleinController {
    use ChunkNotFoundHandlerTrait;

    /**
     * @var ProjectStruct
     */
    private $project;

    /**
     * @return ProjectStruct
     */
    public function getProject() {
        return $this->project;
    }

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     * @throws NotFoundException
     */
    public function show() {

        $format = new Chunk();
        $format->setUser( $this->user );
        $format->setCalledFromApi( true );

        $this->return404IfTheJobWasDeleted();

        $this->response->json( $format->renderOne( $this->chunk ) );

    }

    /**
     * @throws Exception
     */
    public function delete() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_DELETED );
    }

    /**
     * @throws Exception
     */
    public function cancel() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws Exception
     */
    public function archive() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws Exception
     */
    public function active() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_ACTIVE );
    }

    /**
     * @throws Exception
     */
    protected function changeStatus( $status ) {

        ( new ProjectAccessValidator( $this, $this->project ) )->validate();

        JobDao::updateJobStatus( $this->chunk, $status );
        $lastSegmentsList = SegmentTranslationDao::getMaxSegmentIdsFromJob( $this->chunk );
        SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
        $this->response->json( [ 'code' => 1, 'data' => "OK", 'status' => $status ] );

    }

    /**
     * Perform actions after constructing an instance of the class.
     * This method sets up the necessary validators and performs further actions.
     *
     * @throws Exception If an error occurs during the validation process.
     * @throws NotFoundException If the chunk or project could not be found.
     */
    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->chunk   = $Validator->getChunk();
            $this->project = $Validator->getChunk()->getProject( 60 * 10 );
        } );
        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}