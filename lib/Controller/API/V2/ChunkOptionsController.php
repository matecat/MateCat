<?php

namespace API\V2;


use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;

class ChunkOptionsController extends KleinController {

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

    public function update() {
        $chunk_options_model = new \ChunkOptionsModel( $this->chunk ) ;
        
        $chunk_options_model->setOptions( $this->filteredParams() ) ;
        $chunk_options_model->save(); 
        
        $this->response->json( array( 'options' => $chunk_options_model->toArray() ) ) ;
    }

    protected function afterConstruct() {
        $Validator = new ChunkPasswordValidator( $this ) ;
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );
        $this->appendValidator( $Validator );
    }

    protected function filteredParams() {
        $args = array(
            'speech2text' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'lexiqa' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'tag_projection' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

        $args = array_intersect_key( $args, $this->request->params() );
        $filtered = filter_var_array( $this->request->params(), $args);

        return $filtered;
        
    }
}
