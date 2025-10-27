<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/26/16
 * Time: 12:00 PM
 */

namespace Controller\API\V2;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Jobs\JobStruct;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use View\API\V2\Json\SegmentVersion;


class ChunkTranslationVersionController extends KleinController implements ChunkPasswordValidatorInterface {
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
    public function setChunk( JobStruct $chunk ): ChunkTranslationVersionController {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function index(): void {

        $this->return404IfTheJobWasDeleted();

        $results = TranslationVersionDao::getVersionsForChunk( $this->chunk );

        $this->featureSet->loadForProject( $this->chunk->getProject() );

        $formatted = new SegmentVersion( $this->chunk, $results, false, $this->featureSet );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

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