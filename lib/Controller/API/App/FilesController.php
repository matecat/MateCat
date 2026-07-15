<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Exception;
use InvalidArgumentException;
use Model\Files\FilesJobDao;
use Model\Files\FilesPartsDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use ReflectionException;

class FilesController extends AbstractStatefulKleinController
{

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function segments(): void
    {
        // `file_part_id` has the priority
        if (isset($this->params['file_part_id'])) {
            $filePartId = $this->params['file_part_id'];
            $this->validateInteger($filePartId);
            $this->getFirstAndLastSegmentFromFilePartId($filePartId);
        } elseif (isset($this->params['file_id'])) {
            $fileId = $this->params['file_id'];
            $this->validateInteger($fileId);
            $this->getFirstAndLastSegmentFromFileId($fileId);
        } else {
            $this->response->status()->setCode(500);
            $this->response->json([
                'error' => 'Missing parameters. `file_part_id` or `file_id` must be provided'
            ]);
        }
    }

    /**
     * @param int $filePartId
     *
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    private function getFirstAndLastSegmentFromFilePartId(int $filePartId): void
    {
        // ownership gate: the file part must belong to a file assigned to the caller's
        // authenticated chunk, otherwise a guessed file_part_id would leak other tenants' segments
        $filesJobDao = new FilesJobDao($this->getDatabase());
        if (!$filesJobDao->isFilePartInJob($filePartId, (int)$this->chunk->id)) {
            throw new NotFoundException('File part id ' . $filePartId . ' was not found');
        }

        $filePartsDao = new FilesPartsDao($this->getDatabase());
        $firstAndLastSegment = $filePartsDao->getFirstAndLastSegment($filePartId);

        if ($firstAndLastSegment === null || $firstAndLastSegment->first_segment === null) {
            throw new NotFoundException('File part id ' . $filePartId . ' was not found');
        }

        $this->response->json([
            'first_segment' => (int)$firstAndLastSegment->first_segment,
            'last_segment' => (int)$firstAndLastSegment->last_segment,
        ]);
    }

    /**
     * @param int $fileId
     *
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    private function getFirstAndLastSegmentFromFileId(int $fileId): void
    {
        $fileInfo = (new JobDao($this->getDatabase()))->getFilesInfoInJob($this->chunk, 60 * 5);

        if (empty($fileInfo)) {
            throw new NotFoundException('File id ' . $fileId . ' was not found');
        }

        $firstAndLastSegment = array_filter($fileInfo, function ($item) use ($fileId) {
            return $item->id_file == $fileId;
        })[0];

        $this->response->json([
            'fist_segment' => (int)$firstAndLastSegment->first_segment,
            'last_segment' => (int)$firstAndLastSegment->last_segment,
        ]);
    }

    /**
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    private function validateInteger(mixed $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            throw new InvalidArgumentException('`file_part_id` is not an integer');
        }
    }

    protected function registerValidators(): void
    {
        $Validator = (new ChunkPasswordValidator($this));
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });

        $this->appendValidator($Validator);
    }
}