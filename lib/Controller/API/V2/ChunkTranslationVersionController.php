<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/26/16
 * Time: 12:00 PM
 */

namespace API\V2;

use API\Commons\Validators\ChunkPasswordValidator;
use API\Commons\Validators\LoginValidator;
use API\V2\Json\SegmentVersion as JsonFormatter;
use Features\TranslationVersions\Model\TranslationVersionDao;
use Jobs_JobStruct;


class ChunkTranslationVersionController extends BaseChunkController {

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    public function index() {

        $this->return404IfTheJobWasDeleted();

        $results = TranslationVersionDao::getVersionsForChunk( $this->chunk );

        $this->featureSet->loadForProject( $this->chunk->getProject() );

        $formatted = new JsonFormatter( $this->chunk, $results, false, $this->featureSet );

        $this->response->json( array(
                'versions' => $formatted->render()
        )) ;

    }

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this ) ;
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}