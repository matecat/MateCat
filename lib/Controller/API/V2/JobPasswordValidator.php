<?php

namespace API\V2  ;

/**
 * @deprecated use Validators\ChunkPasswordValidator
 */

use Chunks_ChunkDao ;
use Klein\Request ;

class JobPasswordValidator {
    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk ;

    private $id_job;
    private $password ;

    public function __construct( Request $request  ) {
        $this->id_job = $request->id_job ;
        $this->password = $request->password ;
    }

    public function validate() {
        $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
            $this->id_job,
            $this->password
        );

    }

    public function getChunk() {
        return $this->chunk ;
    }

}
