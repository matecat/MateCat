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

namespace Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Exceptions\NotFoundException;
use Jobs_JobDao;
use Jobs_JobStruct;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use ReflectionException;

class ChunkPasswordValidator extends Base {
    /**
     * @var ?Jobs_JobStruct
     */
    protected ?Jobs_JobStruct $chunk = null;

    /**
     * @var ?ChunkReviewStruct
     */
    protected ?ChunkReviewStruct $chunkReview = null;

    protected int    $id_job;
    protected string $password;
    protected ?int   $revision_number = null;

    public function __construct( KleinController $controller ) {

        parent::__construct( $controller );

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
     * @throws ReflectionException
     */
    protected function _validate(): void {

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
        $this->chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $this->request->param( 'password' ), $this->request->param( 'id_job' ) );
        if ( empty( $this->chunkReview ) ) {
            throw new NotFoundException( 'Revision record not found' );
        }
        $this->chunk = $this->chunkReview->getChunk();
        $this->chunk->setIsReview( true );
        $this->chunk->setSourcePage( $this->chunkReview->source_page );
    }

    /**
     * @throws ReflectionException
     */
    protected function getChunkFromTranslatePassword() {
        $this->chunk = Jobs_JobDao::getByIdAndPassword( $this->request->param( 'id_job' ), $this->request->param( 'password' ) );
        if ( !empty( $this->chunk ) ) {
            $this->chunkReview = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk )[ 0 ] ?? null;
        }
    }

    public function getChunk(): Jobs_JobStruct {
        return $this->chunk;
    }

    /**
     * @return int
     */
    public function getJobId(): int {
        return $this->id_job;
    }

    public function getChunkReview(): ChunkReviewStruct {
        return $this->chunkReview;
    }

}
