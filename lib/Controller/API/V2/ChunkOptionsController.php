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
        
        $chunk_options_model->setOptions( $this->request->params() ) ; 
        $chunk_options_model->save(); 
        
        $this->response->json( array( 'options' => $chunk_options_model->toArray() ) ) ;
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ChunkPasswordValidator( $this->request );
    }

    protected function validateRequest() {
        $this->validator->validate();
    }
}
