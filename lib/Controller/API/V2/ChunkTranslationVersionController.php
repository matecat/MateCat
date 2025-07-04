<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/26/16
 * Time: 12:00 PM
 */

namespace Controller\API\V2;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Features\TranslationVersions\Model\TranslationVersionDao;
use Model\Jobs\JobStruct;
use View\API\V2\Json\SegmentVersion as JsonFormatter;


class ChunkTranslationVersionController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    /**
     * @param \Model\Jobs\JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function index() {

        $this->return404IfTheJobWasDeleted();

        $results = TranslationVersionDao::getVersionsForChunk( $this->chunk );

        $this->featureSet->loadForProject( $this->chunk->getProject() );

        $formatted = new JsonFormatter( $this->chunk, $results, false, $this->featureSet );

        $this->response->json( [
                'versions' => $formatted->render()
        ] );

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