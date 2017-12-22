<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/26/16
 * Time: 12:00 PM
 */

namespace API\V2;

use API\V2\Json\SegmentVersion as JsonFormatter;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;


class ChunkTranslationVersionController extends KleinController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    public function index() {

        $results = \Translations_TranslationVersionDao::getVersionsForChunk( $this->chunk );

        $formatted = new JsonFormatter( $results );

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
    }

}