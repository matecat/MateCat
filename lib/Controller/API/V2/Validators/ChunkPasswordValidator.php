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
use Chunks_ChunkStruct;
use Exceptions\NotFoundException;
use Jobs_JobDao;
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
    protected $revision_number;

    public function __construct( KleinController $controller ) {

        parent::__construct( $controller->getRequest() );

        $filterArgs = [
                'id_job'          => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
                'password'        => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'revision_number' => [
                        'filter' => FILTER_SANITIZE_NUMBER_INT
                ],
        ];

        $postInput = (object)filter_var_array( $controller->getParams(), $filterArgs );

        $this->id_job   = $postInput->id_job;
        $this->password = $postInput->password;

        $controller->id_job   = $this->id_job;
        $controller->password = $this->password;

        if ( false === empty( $postInput->revision_number ) ) {
            $this->revision_number       = $postInput->revision_number;
            $controller->revision_number = $this->revision_number;
        }

    }

    /**
     * @return void
     * @throws NotFoundException
     */
    protected function _validate() {

        //try with translate password
        $this->getChunkFromTranslatePassword();
        if ( empty( $this->chunk ) ) {
            //try with review password
            $this->getChunkFromRevisePassword();
        }

    }

    /**
     * @throws NotFoundException
     */
    protected function getChunkFromRevisePassword() {
        $this->chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $this->request->password, $this->request->id_job );
        if ( empty( $this->chunkReview ) ) {
            throw new NotFoundException( 'Revision record not found' );
        }
        $this->chunk = $this->chunkReview->getChunk();
        $this->chunk->setIsReview( true );
        $this->chunk->setSourcePage( $this->chunkReview->source_page );
    }

    /**
     * @throws NotFoundException
     */
    protected function getChunkFromTranslatePassword() {
        $this->chunk = Jobs_JobDao::getByIdAndPassword( $this->request->id_job, $this->request->password, 0, new Chunks_ChunkStruct );
        if ( !empty( $this->chunk ) ) {
            $this->chunkReview = @( new ChunkReviewDao() )->findChunkReviews( $this->chunk )[ 0 ];
        }
    }

    public function getChunk() {
        return $this->chunk;
    }

    public function getJobId() {
        return $this->id_job;
    }

    public function getChunkReview() {
        return $this->chunkReview;
    }

}
