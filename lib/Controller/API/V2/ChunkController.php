<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:39
 */

namespace API\V2;

use API\V2\Json\Chunk;
use API\V2\Validators\ChunkPasswordValidator;
use API\V2\Validators\ProjectAccessValidator;
use Chunks_ChunkStruct;
use Constants_JobStatus;
use Exception;
use Exceptions\NotFoundException;
use Jobs_JobDao;
use Projects_ProjectStruct;
use Translations_SegmentTranslationDao;
use Utils;

class ChunkController extends BaseChunkController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    /**
     * @return Projects_ProjectStruct
     */
    public function getProject() {
        return $this->project;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
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

        $this->changeStatus( Constants_JobStatus::STATUS_DELETED );
    }

    /**
     * @throws Exception
     */
    public function cancel() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_CANCELLED );
    }

    /**
     * @throws Exception
     */
    public function archive() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_ARCHIVED );
    }

    /**
     * @throws Exception
     */
    public function active() {
        $this->return404IfTheJobWasDeleted();

        $this->changeStatus( Constants_JobStatus::STATUS_ACTIVE );
    }

    /**
     * @throws Exception
     */
    protected function changeStatus( $status ) {

        ( new ProjectAccessValidator( $this, $this->project ) )->validate();

        Jobs_JobDao::updateJobStatus( $this->chunk, $status );
        $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $this->chunk );
        Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
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
    }

}