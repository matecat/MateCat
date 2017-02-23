<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/02/2017
 * Time: 10:39
 */

namespace API\V2;

use API\V2\Json\Chunk;
use API\V2\Validators\ChunkPasswordValidator;

class ChunkController extends KleinController {

    /**
     * @var ChunkPasswordValidator
     */
    protected $validator;

    public function show() {
        $chunk = $this->validator->getChunk();
        $this->response->json( Chunk::renderOne($chunk) );
    }

    protected function afterConstruct() {
        $this->validator = new ChunkPasswordValidator( $this->request ) ;
        $this->validator->validate();
    }

}