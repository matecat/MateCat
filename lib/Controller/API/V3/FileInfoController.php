<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\Hook\Event\Filter\DecodeInstructionsEvent;
use Model\Files\FilesInfoUtility;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;


class FileInfoController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $Validator = new ChunkPasswordValidator($this);
        $Validator->onSuccess(function () use ($Validator) {
            $this->chunk = $Validator->getChunk();
            $this->project = $Validator->getChunk()->getProject(new ProjectDao($this->getDatabase()));
            $this->appendValidator(new ProjectAccessValidator($this, $this->project));
        });
        $this->appendValidator($Validator);
    }

    /**
     * @throws RuntimeException
     * @throws ReflectionException
     */
    protected function createFilesInfoUtility(JobStruct $chunk): FilesInfoUtility
    {
        return new FilesInfoUtility($chunk, $this->getDatabase());
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws RuntimeException
     */
    public function getInfo(): void
    {
        $this->return404IfTheJobWasDeleted();

        $filesInfoUtility = $this->createFilesInfoUtility($this->chunk);
        $this->response->json($filesInfoUtility->getInfo());
    }

    /**
     * @throws NotFoundException|ReflectionException
     * @throws Exception
     * @throws RuntimeException
     */
    public function getInstructions(): void
    {
        $this->return404IfTheJobWasDeleted();

        $id_file = $this->request->param('id_file');
        $filesInfoUtility = $this->createFilesInfoUtility($this->chunk);
        $instructions = $filesInfoUtility->getInstructions($id_file);

        if (!$instructions) {
            throw new NotFoundException('No instructions for this file');
        }

        $this->response->json($instructions);
    }

    /**
     * @throws NotFoundException|ReflectionException
     * @throws Exception
     * @throws RuntimeException
     */
    public function getInstructionsByFilePartsId(): void
    {
        $this->return404IfTheJobWasDeleted();

        $id_file = $this->request->param('id_file');
        $id_file_parts = $this->request->param('id_file_parts');
        $filesInfoUtility = $this->createFilesInfoUtility($this->chunk);
        $instructions = $filesInfoUtility->getInstructions($id_file, $id_file_parts);

        if (!$instructions) {
            throw new NotFoundException('No instructions for this file parts id');
        }

        $this->response->json($instructions);
    }

    /**
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws PDOException
     * @throws ReQueueException
     * @throws ReflectionException
     * @throws ValidationError
     * @throws \Model\Exceptions\NotFoundException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function setInstructions(): void
    {
        $this->return404IfTheJobWasDeleted();

        $id_file = $this->request->param('id_file');
        $instructions = $this->request->param('instructions');
        $filesInfoUtility = $this->createFilesInfoUtility($this->chunk);

        $decodeInstructionsEvent = new DecodeInstructionsEvent($instructions);
        $this->featureSet->dispatch($decodeInstructionsEvent);
        $instructions = $decodeInstructionsEvent->getValue();

        if (empty($instructions)) {
            throw new InvalidArgumentException("Empty instructions provided");
        }

        if ($filesInfoUtility->setInstructions($id_file, $instructions)) {
            $this->response->json([
                "success" => true,
            ]);
        } else {
            throw new NotFoundException('File not found on this project');
        }
    }
}

// GET https://dev.matecat.com/api/v3/jobs/32/f7ac6b279743/file/35/instructions
// POST https://dev.matecat.com/api/v3/jobs/32/f7ac6b279743/file/35/instructions
