<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace API\V3;

use API\V2\BaseChunkController;
use API\V2\Validators\ChunkPasswordValidator;
use API\V3\Json\Chunk;
use Chunks_ChunkStruct;
use Exception;
use Exceptions\NotFoundException;
use Projects_ProjectStruct;

class ChunkController extends BaseChunkController {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

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
     * @param Projects_ProjectStruct $project
     *
     * @return $this
     */
    public function setProject( $project ) {
        $this->project = $project;

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

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->setChunk( $Validator->getChunk() );
            $this->setProject( $Validator->getChunk()->getProject() );
            $this->setFeatureSet( $this->project->getFeaturesSet() );
        } );

        $this->appendValidator( $Validator );
    }

}