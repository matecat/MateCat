<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Model\ReviseFeedback\FeedbackDAO;
use Model\ReviseFeedback\FeedbackStruct;

class RevisionFeedbackController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     */
    public function feedback(): void
    {
        // insert or update feedback
        $feedbackStruct                  = new FeedbackStruct();
        $feedbackStruct->id_job          = $this->request->param('id_job');
        $feedbackStruct->password        = $this->request->param('password');
        $feedbackStruct->revision_number = $this->request->param('revision_number');
        $feedbackStruct->feedback        = $this->request->param('feedback');

        $this->return404IfTheJobWasDeleted();

        $rows   = (new FeedbackDAO())->insertOrUpdate($feedbackStruct);
        $status = ($rows > 0) ? 'ok' : 'ko';

        $this->response->json([
                'status' => $status
        ]);
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $validator = new ChunkPasswordValidator($this);
        $validator->onSuccess(function () use ($validator) {
            $this->chunk = $validator->getChunk();
        });

        $this->appendValidator($validator);
    }
}

