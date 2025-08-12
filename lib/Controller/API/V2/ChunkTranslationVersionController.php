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
use Model\Jobs\JobStruct;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use View\API\V2\Json\SegmentVersion;


class ChunkTranslationVersionController extends KleinController {
    use ChunkNotFoundHandlerTrait;
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
    public function index() {

        $this->return404IfTheJobWasDeleted();

        $results = TranslationVersionDao::getVersionsForChunk( $this->chunk );

        $this->featureSet->loadForProject( $this->chunk->getProject() );

        $formatted = new SegmentVersion( $this->chunk, $results, false, $this->featureSet );

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