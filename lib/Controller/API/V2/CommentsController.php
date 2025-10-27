<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/05/16
 * Time: 15:08
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Comments\CommentDao;
use Model\Jobs\JobStruct;

class CommentsController extends KleinController implements ChunkPasswordValidatorInterface {
    use ChunkNotFoundHandlerTrait;

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }

    /**
     * @param JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( JobStruct $chunk ): CommentsController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function index(): void {

        $this->return404IfTheJobWasDeleted();

        $comments = CommentDao::getCommentsForChunk( $this->chunk, [
                'from_id' => $this->request->param( 'from_id' )
        ] );

        $this->response->json( [ 'comments' => $comments ] );
    }

    protected function afterConstruct(): void {
        $Validator  = new ChunkPasswordValidator( $this );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}