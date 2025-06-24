<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/05/16
 * Time: 15:08
 */

namespace Controller\API\V2;

use Comments_CommentDao;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Jobs_JobStruct;

class CommentsController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( Jobs_JobStruct $chunk ): CommentsController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function index() {

        $this->return404IfTheJobWasDeleted();

        $comments = Comments_CommentDao::getCommentsForChunk( $this->chunk, [
                'from_id' => $this->request->param( 'from_id' )
        ] );

        $this->response->json( [ 'comments' => $comments ] );
    }

    protected function afterConstruct() {
        $Validator  = new ChunkPasswordValidator( $this );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}