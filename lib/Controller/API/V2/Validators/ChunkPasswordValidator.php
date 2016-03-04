<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/24/16
 * Time: 12:01 PM
 */

/**
 *
 * This validator is to be used when we want to check that the
 */

namespace API\V2\Validators;

use Chunks_ChunkDao ;
use Klein\Request ;

class ChunkPasswordValidator extends Base {
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
