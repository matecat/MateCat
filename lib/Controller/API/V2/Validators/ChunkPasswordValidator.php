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
use Chunks_ChunkDao;
use Exceptions\NotFoundException;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;

class ChunkPasswordValidator extends Base {
    /**
     * @var \Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunkReview;

    protected $id_job;
    protected $password;

    public function __construct( KleinController $controller ) {

        parent::__construct( $controller->getRequest() );

        $filterArgs = [
                'id_job'   => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $postInput = (object)filter_var_array( $controller->getParams(), $filterArgs );

        $this->id_job   = $postInput->id_job;
        $this->password = $postInput->password;

        $controller->id_job   = $this->id_job;
        $controller->password = $this->password;

    }

    /**
     * @return mixed|void
     * @throws NotFoundException
     */
    protected function _validate() {
        try {
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword(
                    $this->id_job,
                    $this->password
            );
        } catch ( NotFoundException $e ) {
            $this->chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId(
                    $this->password,
                    $this->id_job
            );
            if ( $this->chunkReview ) {
                $this->chunk = $this->chunkReview->getChunk();
                $this->chunk->setIsReview( true );
                $this->chunk->setSourcePage( $this->chunkReview->source_page );
            } else {
                throw new NotFoundException( 'Record not found' );
            }
        }
    }

    public function getChunk() {
        return $this->chunk;
    }

    public function getJobId() {
        return $this->id_job;
    }

    public function getChunkReview(){
        return $this->chunkReview;
    }

}
