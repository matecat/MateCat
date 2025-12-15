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
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use ReflectionException;

class ChunkPasswordValidator extends Base
{
    /**
     * @var ?JobStruct
     */
    protected ?JobStruct $chunk = null;

    /**
     * @var ?ChunkReviewStruct
     */
    protected ?ChunkReviewStruct $chunkReview = null;

    protected int $id_job;
    protected string $password;
    protected ?int $revision_number = null;
    private int $ttl;

    public function __construct(KleinController $controller, int $ttl)
    {
        parent::__construct($controller);

        $filterArgs = [
            'id_job' => [
                'filter' => FILTER_SANITIZE_NUMBER_INT
            ],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ],
            'revision_number' => [
                'filter' => FILTER_SANITIZE_NUMBER_INT
            ],
        ];

        $postInput = (object)filter_var_array($controller->getParams(), $filterArgs);

        $this->id_job = $postInput->id_job;
        $this->password = $postInput->password;
        $this->ttl = $ttl;

        if (false === empty($postInput->revision_number)) {
            $this->revision_number = $postInput->revision_number;
        }
    }

    /**
     * @return void
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function _validate(): void
    {
        //try with the Translate password
        $this->getChunkFromTranslatePassword();
        if (empty($this->chunk)) {
            //try with the Review password
            $this->getChunkFromRevisePassword();
        }
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function getChunkFromRevisePassword(): void
    {
        $this->chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId($this->request->param('password'), $this->request->param('id_job'), $this->ttl);
        if (empty($this->chunkReview)) {
            throw new NotFoundException('Not found.');
        }
        $this->chunk = ChunkDao::getByIdAndPassword($this->chunkReview->id_job, $this->chunkReview->password, $this->ttl);
        $this->chunk->setIsReview(true);
        $this->chunk->setSourcePage($this->chunkReview->source_page);
    }

    /**
     * @throws ReflectionException
     */
    protected function getChunkFromTranslatePassword(): void
    {
        $this->chunk = JobDao::getByIdAndPassword($this->request->param('id_job'), $this->request->param('password'), $this->ttl);
        if (!empty($this->chunk)) {
            $this->chunkReview = (new ChunkReviewDao())->findChunkReviews($this->chunk, $this->ttl)[0] ?? null;
        }
    }

    public function getChunk(): JobStruct
    {
        return $this->chunk;
    }

    /**
     * @return int
     */
    public function getJobId(): int
    {
        return $this->id_job;
    }

    public function getChunkReview(): ChunkReviewStruct
    {
        return $this->chunkReview;
    }

}
