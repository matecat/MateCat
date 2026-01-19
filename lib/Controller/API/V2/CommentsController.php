<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/05/16
 * Time: 15:08
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Comments\CommentDao;

class CommentsController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     * @throws Exception
     */
    public function index(): void
    {
        $this->return404IfTheJobWasDeleted();

        $comments = CommentDao::getCommentsForChunk($this->chunk, [
            'from_id' => $this->request->param('from_id')
        ]);

        $this->response->json(['comments' => $comments]);
    }

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });
        $this->appendValidator($Validator);
    }

}