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

use API\V2\KleinController;
use Chunks_ChunkDao ;
use Klein\Request ;
use Symfony\Component\Config\Definition\Exception\Exception;

class ChunkPasswordValidator extends Base {
    /**
     * @var \Chunks_ChunkStruct
     */
    protected $chunk ;

    protected $id_job;
    protected $password ;

    public function __construct( KleinController $controller ) {

        parent::__construct( $controller->getRequest() );

        $filterArgs = array(
                'id_job' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ),
                'password'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = (object)filter_var_array( $controller->getParams(), $filterArgs );

        $this->id_job = $postInput->id_job;
        $this->password   = $postInput->password;

        $controller->id_job   = $this->id_job;
        $controller->password = $this->password;

    }

    /**
     * @return mixed|void
     * @throws \Exceptions\NotFoundException
     */
    protected function _validate() {
        try {
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
                    $this->id_job,
                    $this->password
            );
        } catch ( \Exceptions\NotFoundException $e ) {
            $review_chunk = \LQA\ChunkReviewDao::findByReviewPasswordAndJobId(
                    $this->password,
                    $this->id_job
            );
            if ( $review_chunk ) {
                $this->chunk = $review_chunk->getChunk();
                $this->chunk->setIsReview( true );
            } else {
                throw new \Exceptions\NotFoundException( 'Record not found' );
            }
        }
    }

    public function getChunk() {
        return $this->chunk ;
    }

    public function getJobId(){
        return $this->id_job;
    }

}
