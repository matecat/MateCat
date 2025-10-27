<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/24/16
 * Time: 11:56 AM
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Model\Jobs\JobStruct;
use Model\LQA\EntryDao;
use View\API\V2\Json\SegmentTranslationIssue as JsonFormatter;

class ChunkTranslationIssueController extends KleinController implements ChunkPasswordValidatorInterface {
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
    public function setChunk( JobStruct $chunk ): static {
        $this->chunk = $chunk;

        return $this;
    }

    public function index(): void {

        $this->return404IfTheJobWasDeleted();

        // find all issues by chunk and return the json representation.
        $result = EntryDao::findAllByChunk( $this->chunk );

        $json     = new JsonFormatter();
        $rendered = $json->render( $result );

        $this->response->json( [ 'issues' => $rendered ] );
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