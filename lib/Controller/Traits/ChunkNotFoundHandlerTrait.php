<?php

namespace Controller\Traits;

use Controller\Exceptions\RenderTerminatedException;
use Exception;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use ReflectionException;
use Utils\Registry\AppConfig;

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
        $job = (new JobDao($this->db()))->getByIdAndPassword($id_job, $password);

        if (null === $job) {
            $chunkReview = (new ChunkReviewDao($this->db()))->findByReviewPasswordAndJobId($password, $id_job);
            if ($chunkReview) {
                $job = $chunkReview->getChunk();
            }
        }

        return $job;
    }

    /**
     * Return 404 if chunk was deleted
     *
     * @throws RenderTerminatedException
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

            // Production terminates the request after the 404 has been sent.
            // Under tests a throwable is raised instead so the PHPUnit worker
            // survives and the branch is assertable (matches BaseKleinViewController
            // and DownloadQRController). RenderTerminatedException is unchecked.
            if (AppConfig::$ENV === 'testing') {
                throw new RenderTerminatedException();
            }

            exit();
        }
    }
}