<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace Controller\API\V3;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use View\API\V3\Json\Chunk;

class ChunkController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;
    /**
     * @var ChunkReviewStruct[]
     */
    private array $chunk_reviews;

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): ChunkController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @param ProjectStruct $project
     *
     * @return $this
     */
    public function setProject( ProjectStruct $project ): ChunkController {
        $this->project = $project;

        return $this;
    }

    /**
     * @param ChunkReviewStruct[] $chunk_reviews
     *
     * @return $this
     */
    public function setChunkReviews( array $chunk_reviews ): ChunkController {
        $this->chunk_reviews = $chunk_reviews;

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
        $format->setChunkReviews( $this->chunk_reviews );

        $this->return404IfTheJobWasDeleted();

        $this->response->json( $format->renderOne( $this->chunk ) );

    }

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );

        $Validator = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Validator ) {
            $this->setChunk( $Validator->getChunk() );
            $this->setProject( $Validator->getChunk()->getProject() );
            $this->setFeatureSet( $this->project->getFeaturesSet() );
            $this->setChunkReviews( ( new ChunkReviewDao() )->findChunkReviews( $Validator->getChunk() ) );
        } );
        $this->appendValidator( $Validator );
    }

}