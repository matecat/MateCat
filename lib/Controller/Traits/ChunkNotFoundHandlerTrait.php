<?php

namespace Controller\Traits;

use Exception;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use ReflectionException;

trait ChunkNotFoundHandlerTrait
{

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @param int $id_job
     * @param string $password
     *
     * @return ?JobStruct
     * @throws ReflectionException
     * @throws Exception
     */
    protected function getJob(int $id_job, string $password): ?JobStruct
    {
        $job = (new JobDao())->getByIdAndPassword($id_job, $password);

        if (null === $job) {
            $chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId($password, $id_job);
            if ($chunkReview) {
                $job = $chunkReview->getChunk();
            }
        }

        return $job;
    }

    /**
     * Return 404 if chunk was deleted
     */
    protected function return404IfTheJobWasDeleted(): void
    {
        if ($this->chunk->isDeleted()) {
            $this->response->status()->setCode(404);
            $this->response->json([
                'errors' => [
                    'code' => 0,
                    'message' => 'No job found.'
                ]
            ]);
            exit();
        }
    }
}