<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use InvalidArgumentException;
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
     */
    public function segments(): void
    {
        // `file_part_id` has the priority
        if (isset($_POST[ 'file_part_id' ])) {
            $filePartId = $_POST[ 'file_part_id' ];
            $this->validateInteger($filePartId);
            $this->getFirstAndLastSegmentFromFilePartId($filePartId);
        } elseif (isset($_POST[ 'file_id' ])) {
            $fileId = $_POST[ 'file_id' ];
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
     */
    private function getFirstAndLastSegmentFromFilePartId(int $filePartId): void
    {
        $filePartsDao        = new FilesPartsDao();
        $firstAndLastSegment = $filePartsDao->getFirstAndLastSegment($filePartId);

        if (null === $firstAndLastSegment->first_segment) {
            throw new NotFoundException('File part id ' . $filePartId . ' was not found');
        }

        $this->response->json([
                'first_segment' => (int)$firstAndLastSegment->first_segment,
                'last_segment'  => (int)$firstAndLastSegment->last_segment,
        ]);
    }

    /**
     * @param int $fileId
     *
     * @throws ReflectionException
     * @throws NotFoundException
     */
    private function getFirstAndLastSegmentFromFileId(int $fileId): void
    {
        $fileInfo = JobDao::getFirstSegmentOfFilesInJob($this->chunk, 60 * 5);

        if (empty($fileInfo)) {
            throw new NotFoundException('File id ' . $fileId . ' was not found');
        }

        $firstAndLastSegment = array_filter($fileInfo, function ($item) use ($fileId) {
            return $item->id_file == $fileId;
        })[ 0 ];

        $this->response->json([
                'fist_segment' => (int)$firstAndLastSegment->first_segment,
                'last_segment' => (int)$firstAndLastSegment->last_segment,
        ]);
    }

    /**
     * @param mixed $value
     */
    private function validateInteger(mixed $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_INT)) {
            throw new InvalidArgumentException('`file_part_id` is not an integer');
        }
    }

    protected function afterConstruct(): void
    {
//        $this->appendValidator(new LoginValidator($this));
        $Validator = (new ChunkPasswordValidator($this));
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
        });

        $this->appendValidator($Validator);
    }
}