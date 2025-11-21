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
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use View\API\V3\Json\Chunk;

class ChunkController extends KleinController
{
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
     * @throws Exception
     * @throws NotFoundException
     */
    public function show(): void
    {
        $format = new Chunk();
        $format->setUser($this->user);
        $format->setCalledFromApi(true);
        $format->setChunkReviews($this->chunk_reviews);

        $this->return404IfTheJobWasDeleted();

        $this->response->json($format->renderOne($this->chunk));
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk         = $Validator->getChunk();
            $this->project       = $Validator->getChunk()->getProject();
            $this->featureSet    = $this->project->getFeaturesSet();
            $this->chunk_reviews = (new ChunkReviewDao())->findChunkReviews($Validator->getChunk());
        });
        $this->appendValidator($Validator);
    }

}