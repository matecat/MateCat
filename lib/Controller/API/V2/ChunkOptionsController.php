<?php

namespace API\V2;


use API\Commons\Validators\ChunkPasswordValidator;
use API\Commons\Validators\LoginValidator;
use Exception;
use Jobs_JobStruct;
use Projects\ChunkOptionsModel;

class ChunkOptionsController extends BaseChunkController {

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return $this
     */
    public function setChunk( Jobs_JobStruct $chunk ): ChunkOptionsController {
        $this->chunk = $chunk;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function update() {

        $this->return404IfTheJobWasDeleted();

        $chunk_options_model = new ChunkOptionsModel( $this->chunk ) ;

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
        $this->appendValidator( new LoginValidator( $this ) );
    }

    protected function filteredParams() {
        $args = array(
            'speech2text' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'lexiqa' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'tag_projection' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

        $args = array_intersect_key( $args, $this->request->params() );

        return filter_var_array( $this->request->params(), $args);

    }
}
