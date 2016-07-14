<?php

namespace API\V2;


use API\V2\Validators\ChunkPasswordValidator;

class ChunkOptionsController extends ProtectedKleinController {

    /**
     * @var Validators\ChunkPasswordValidator
     */
    private $validator;

    public function update() {
        $chunk_options_model = new \ChunkOptionsModel( $this->validator->getChunk() ) ; 
        
        $chunk_options_model->setOptions( $this->filteredParams() ) ;
        $chunk_options_model->save(); 
        
        $this->response->json( array( 'options' => $chunk_options_model->toArray() ) ) ;
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ChunkPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
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
