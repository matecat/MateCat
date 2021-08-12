<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/24/16
 * Time: 11:56 AM
 */

namespace API\V2;

use API\V2\Json\SegmentTranslationIssue as JsonFormatter;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;

class ChunkTranslationIssueController extends BaseChunkController {

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

        $this->return404IfTheJobWasDeleted();

        // find all issues by chunk and return the json representation.
        $result = \LQA\EntryDao::findAllByChunk( $this->chunk );

        $json     = new JsonFormatter();
        $rendered = $json->render( $result );

        $this->response->json( array( 'issues' => $rendered ) );
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