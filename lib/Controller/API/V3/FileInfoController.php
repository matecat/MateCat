<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exceptions\ValidationError;
use Files\FilesInfoUtility;
use Jobs_JobStruct;
use Projects_ProjectStruct;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;


class FileInfoController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->setChunk( $Validator->getChunk() );
            $this->setProject( $Validator->getChunk()->getProject() );
            $this->appendValidator( new ProjectAccessValidator( $this, $Validator->getChunk()->getProject() ) );
            //those are not needed at moment, so avoid unnecessary queries
//            $this->setFeatureSet( $this->project->getFeaturesSet() );
        } );
        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );

    }

    private function setChunk( Jobs_JobStruct $chunk ) {
        $this->chunk = $chunk;
    }

    private function setProject( Projects_ProjectStruct $project ) {
        $this->project = $project;
    }

    public function getInfo() {

        // those values where not used
//        $page    = ( isset( $this->request->page ) ) ? $this->request->page : 1;
//        $perPage = ( isset( $this->request->per_page ) ) ? $this->request->per_page : 200;

        $this->return404IfTheJobWasDeleted();

        $filesInfoUtility = new FilesInfoUtility( $this->chunk );
        $this->response->json( $filesInfoUtility->getInfo() );
    }

    /**
     * @throws NotFoundException
     */
    public function getInstructions() {

        $this->return404IfTheJobWasDeleted();

        $id_file          = $this->request->param( 'id_file' );
        $filesInfoUtility = new FilesInfoUtility( $this->chunk );
        $instructions     = $filesInfoUtility->getInstructions( $id_file );

        if ( !$instructions ) {
            throw new NotFoundException( 'No instructions for this file' );
        }

        $this->response->json( $instructions );
    }

    /**
     * @throws NotFoundException
     */
    public function getInstructionsByFilePartsId() {

        $this->return404IfTheJobWasDeleted();

        $id_file          = $this->request->param( 'id_file' );
        $id_file_parts    = $this->request->param( 'id_file_parts' );
        $filesInfoUtility = new FilesInfoUtility( $this->chunk );
        $instructions     = $filesInfoUtility->getInstructions( $id_file, $id_file_parts );

        if ( !$instructions ) {
            throw new NotFoundException( 'No instructions for this file parts id' );
        }

        $this->response->json( $instructions );
    }

    /**
     * save instructions
     *
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    public function setInstructions() {

        $this->return404IfTheJobWasDeleted();

        $id_file          = $this->request->param( 'id_file' );
        $instructions     = $this->request->param( 'instructions' );
        $filesInfoUtility = new FilesInfoUtility( $this->chunk );

        $instructions = $this->featureSet->filter( 'decodeInstructions', $instructions );

        if ( $filesInfoUtility->setInstructions( $id_file, $instructions ) ) {
            $this->response->json( true );
        } else {
            throw new NotFoundException( 'File not found on this project' );
        }
    }
}

// GET https://dev.matecat.com/api/v3/jobs/32/f7ac6b279743/file/35/instructions
// POST https://dev.matecat.com/api/v3/jobs/32/f7ac6b279743/file/35/instructions