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
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use TypeError;

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

    public function __construct(KleinController $controller, int $ttl = 0)
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

        $this->id_job = (int)($postInput->id_job ?? 0);
        $this->password = (string)($postInput->password ?? '');
        $this->ttl = $ttl;

        if (false === empty($postInput->revision_number)) {
            $this->revision_number = (int)$postInput->revision_number;
        }
    }

    /**
     * @return void
     * @throws Exception
     * @throws NotFoundException
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
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
     * @throws Exception
     * @throws NotFoundException
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    protected function getChunkFromRevisePassword(): void
    {
        $this->chunkReview = (new ChunkReviewDao($this->controller->getDatabase()))->findByReviewPasswordAndJobId($this->password, $this->id_job, $this->ttl);
        if (empty($this->chunkReview)) {
            throw new NotFoundException('Not found.');
        }
        $this->chunk = (new JobDao($this->controller->getDatabase()))->getByIdAndPasswordOrFail($this->chunkReview->id_job, $this->chunkReview->password, $this->ttl);
        $this->chunk->setIsReview(true);
        $this->chunk->setSourcePage($this->chunkReview->source_page);
    }

    /**
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    protected function getChunkFromTranslatePassword(): void
    {
        $this->chunk = (new JobDao($this->controller->getDatabase()))->getByIdAndPassword($this->id_job, $this->password, $this->ttl);
        if (!empty($this->chunk)) {
            $this->chunkReview = (new ChunkReviewDao($this->controller->getDatabase()))->findChunkReviews($this->chunk, $this->ttl)[0] ?? null;
        }
    }

    /**
     * @throws RuntimeException
     */
    public function getChunk(): JobStruct
    {
        if ($this->chunk === null) {
            throw new RuntimeException('validate() must be called before getChunk()');
        }

        return $this->chunk;
    }

    /**
     * @return int
     */
    public function getJobId(): int
    {
        return $this->id_job;
    }

    public function getChunkReview(): ?ChunkReviewStruct
    {
        return $this->chunkReview;
    }

}
