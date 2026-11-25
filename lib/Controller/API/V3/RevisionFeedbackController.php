<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Model\LQA\ChunkReviewStruct;
use Model\ReviseFeedback\FeedbackDAO;
use Model\ReviseFeedback\FeedbackStruct;
use PDOException;
use Plugins\Features\ReviewExtended\ReviewUtils;
use TypeError;

class RevisionFeedbackController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    private ?ChunkReviewStruct $chunkReview = null;

    /**
     * @throws AuthorizationError
     * @throws TypeError
     * @throws PDOException
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function feedback(): void
    {
        /*
         * The feedback belongs to the revision phase the presented password identifies, so both the
         * row key and the phase number are read from the review record the credential matched and
         * never from a client-declared revision_number (GHSA-7q94-2fmr-3p42). A translate password
         * identifies no revision phase, so it cannot write revision feedback.
         */
        $revisionNumber = ReviewUtils::sourcePageToRevisionNumber($this->chunk->getSourcePage());
        if ($this->chunkReview === null || $revisionNumber === null) {
            throw new AuthorizationError('A revision password is required to submit revision feedback', 401);
        }

        // insert or update feedback
        $feedbackStruct = new FeedbackStruct();
        $feedbackStruct->id_job = $this->chunkReview->id_job;
        $feedbackStruct->password = $this->chunkReview->review_password
            ?? throw new AuthorizationError('A revision password is required to submit revision feedback', 401);
        $feedbackStruct->revision_number = $revisionNumber;
        $feedbackStruct->feedback = $this->request->param('feedback');

        $this->return404IfTheJobWasDeleted();

        $rows = $this->createFeedbackDao()->insertOrUpdate($feedbackStruct);
        $status = ($rows > 0) ? 'ok' : 'ko';

        $this->response->json([
            'status' => $status
        ]);
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $validator = new ChunkPasswordValidator($this);
        $validator->onSuccess(function () use ($validator) {
            $this->chunk = $validator->getChunk();
            $this->chunkReview = $validator->getChunkReview();
        });

        $this->appendValidator($validator);
    }

    protected function createFeedbackDao(): FeedbackDAO
    {
        return new FeedbackDAO($this->getDatabase());
    }
}
