<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:39
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
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
use ReflectionException;
use Throwable;
use Utils\Constants\JobStatus;
use Utils\Tools\Utils;
use View\API\V2\Json\Chunk;

class JobsController extends KleinController implements ChunkPasswordValidatorInterface {
    use ChunkNotFoundHandlerTrait;

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;

    /**
     * @return ProjectStruct
     */
    public function getProject(): ProjectStruct {
        return $this->project;
    }

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): static {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     * @throws NotFoundException
     */
    public function show(): void {

        $format = new Chunk();
        $format->setUser( $this->user );
        $format->setCalledFromApi( true );

        $this->return404IfTheJobWasDeleted();

        $this->response->json( $format->renderOne( $this->chunk ) );

    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function delete(): void {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_DELETED );
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function cancel(): void {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function archive(): void {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function active(): void {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( JobStatus::STATUS_ACTIVE );
    }

    /**
     * @param string $status
     *
     * @throws ReflectionException
     * @throws Throwable
     */
    protected function changeStatus( string $status ): void {

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
     */
    protected function afterConstruct(): void {
        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->chunk   = $Validator->getChunk();
            $this->project = $Validator->getChunk()->getProject( 60 * 10 );
        } );
        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}