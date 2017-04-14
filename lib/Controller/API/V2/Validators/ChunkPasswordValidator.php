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

    public function __construct( Request $request ) {

        $filterArgs = array(
                'id_job' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ),
                'password'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = (object)filter_var_array( $request->params(
                array(
                        'id_job',
                        'password',
                )
        ), $filterArgs );

        $this->id_job = $postInput->id_job;
        $this->password   = $postInput->password;

        $request->id_job = $this->id_job;
        $request->password   = $this->password;

        parent::__construct( $request );
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

    public function getJobId(){
        return $this->id_job;
    }

}
