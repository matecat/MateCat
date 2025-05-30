<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace API\V3;

use API\Commons\Validators\ChunkPasswordValidator;
use API\Commons\Validators\LoginValidator;
use API\V2\BaseChunkController;
use API\V3\Json\Chunk;
use Exception;
use Exceptions\NotFoundException;
use Jobs_JobStruct;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectStruct;

class ChunkController extends BaseChunkController {

    /**
     * @var Projects_ProjectStruct
     */
    protected Projects_ProjectStruct $project;
    /**
     * @var ChunkReviewStruct[]
     */
    private array $chunk_reviews;

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( Jobs_JobStruct $chunk ): ChunkController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @param Projects_ProjectStruct $project
     *
     * @return $this
     */
    public function setProject( Projects_ProjectStruct $project ): ChunkController {
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