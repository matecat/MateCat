<?php

namespace API\V2  ;

class JobPasswordValidator {
    private $chunk ;

    private $id_job;
    private $password ;

    public function __construct( $id_job, $password ) {
        $this->id_job = $id_job;
        $this->password = $password;
    }

    public function validate() {
        try {

            Log::doLog( $this->id_job, $this->password );

            $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
                $this->id_job,
                $this->password
            );
            return true;
        } catch ( Exceptions_RecordNotFound $e ) {
            return false;
        }
    }

    public function getChunk() {
        return $this->chunk ;
    }

}
