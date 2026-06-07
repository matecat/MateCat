<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\Services\RateLimiterService;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use Utils\Tools\Utils;
use Model\Exceptions\NotFoundException;
use Model\Files\FileDao;
use Model\Files\FilesMetadataMarshaller;
use Model\Files\MetadataDao as FilesMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataMarshaller;
use Model\Segments\SegmentStruct;
use ReflectionException;
use Swaggest\JsonSchema\InvalidValue;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class ContextUrlController extends KleinController
{
    protected ?ProjectStruct $project = null;
    protected ProjectsMetadataDao $projectsMetadataDao;
    protected FilesMetadataDao $filesMetadataDao;
    protected SegmentMetadataDao $segmentMetadataDao;
    protected FileDao $fileDao;
    protected SegmentDao $segmentDao;
    protected RateLimiterService $rateLimiterService;

    /**
     * @throws Exception
     */
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $projectPasswordValidator = new ProjectPasswordValidator($this);
        $this->appendValidator(
            $projectPasswordValidator
                ->onSuccess(function () use ($projectPasswordValidator) {
                    $project = $projectPasswordValidator->getProject();
                    if ($project === null) {
                        throw new NotFoundException('Project not found', 404);
                    }
                    $this->project = $project;
                })
                ->onSuccess(function () {
                    $project = $this->getValidatedProject();
                    (new ProjectAccessValidator($this, $project))->validate();
                })
        );

        $this->projectsMetadataDao = new ProjectsMetadataDao();
        $this->filesMetadataDao = new FilesMetadataDao();
        $this->segmentMetadataDao = new SegmentMetadataDao();
        $this->fileDao = new FileDao();
        $this->segmentDao = new SegmentDao();
        $this->rateLimiterService = new RateLimiterService();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws \Swaggest\JsonSchema\Exception
     * @throws InvalidValue
     * @throws Exception
     */
    protected function validateJSON(string $json): array
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator = new JSONValidator('segment_context_url.json', true);
        $validator->validate($validatorObject);
        return $validatorObject->getValue(true);
    }

    /**
     * @throws Exception
     */
    private function checkRateLimit(): bool
    {
        $route = '/api/v3/context-url';
        $identifiers = [
            Utils::getRealIpAddr() ?? '127.0.0.1',
            $this->getUser()->email ?? 'anonymous',
        ];

        foreach ($identifiers as $identifier) {
            $response = $this->rateLimiterService->checkAndIncrement(
                $this->response, $identifier, $route, 10
            );
            if ($response instanceof Response) {
                $this->response = $response;
                return true;
            }
        }

        return false;
    }

    /**
     * @throws NotFoundException
     */
    private function getValidatedProject(): ProjectStruct
    {
        if ($this->project === null) {
            throw new NotFoundException('Project not found', 404);
        }
        return $this->project;
    }

    /**
     * @throws AuthorizationError
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function setForProject(): void
    {
        if ($this->checkRateLimit()) {
            return;
        }

        $project = $this->getValidatedProject();

        $body = $this->request->body();
        if ($body === null) {
            throw new InvalidArgumentException('Missing request body', 400);
        }

        $array = $this->validateJSON($body);
        $contextUrl = $array['context_url'];

        $this->projectsMetadataDao->set(
            (int)$project->id,
            ProjectsMetadataMarshaller::CONTEXT_URL->value,
            $contextUrl
        );

        $this->response->json([
            'level' => 'project',
            'id_project' => (int)$project->id,
            'context_url' => $contextUrl,
        ]);
    }

    /**
     * @throws AuthorizationError
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function setForFile(): void
    {
        if ($this->checkRateLimit()) {
            return;
        }

        $project = $this->getValidatedProject();

        $body = $this->request->body();
        if ($body === null) {
            throw new InvalidArgumentException('Missing request body', 400);
        }

        $array = $this->validateJSON($body);
        $idFile = $array['id_file'] ?? null;
        $contextUrl = $array['context_url'];

        if ($idFile === null) {
            throw new InvalidArgumentException('Missing or invalid id_file', 400);
        }

        $file = $this->fileDao->getById($idFile, 3600);
        if (!$file) {
            throw new NotFoundException('File not found', 404);
        }

        if ($file->id_project !== (int)$project->id) {
            throw new AuthorizationError('File does not belong to this project', 403);
        }

        $idProject = (int)$project->id;
        $existing = $this->filesMetadataDao->get($idProject, $idFile, FilesMetadataMarshaller::CONTEXT_URL->value);
        if ($existing) {
            $this->filesMetadataDao->update($idProject, $idFile, FilesMetadataMarshaller::CONTEXT_URL->value, $contextUrl);
        } else {
            $this->filesMetadataDao->insert($idProject, $idFile, FilesMetadataMarshaller::CONTEXT_URL->value, $contextUrl);
        }

        $this->response->json([
            'level' => 'file',
            'id_project' => $idProject,
            'id_file' => (int)$idFile,
            'context_url' => $contextUrl,
        ]);
    }

    /**
     * @throws AuthorizationError
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function setForSegment(): void
    {
        if ($this->checkRateLimit()) {
            return;
        }

        $project = $this->getValidatedProject();

        $body = $this->request->body();
        if ($body === null) {
            throw new InvalidArgumentException('Missing request body', 400);
        }

        $array = $this->validateJSON($body);
        $idSegment = $array['id_segment'] ?? null;
        $contextUrl = $array['context_url'];

        if ($idSegment === null) {
            throw new InvalidArgumentException('Missing or invalid id_segment', 400);
        }

        $marshalled = SegmentMetadataMarshaller::CONTEXT_URL->marshall($contextUrl);
        if ($marshalled === null) {
            throw new InvalidArgumentException('Invalid context_url value', 400);
        }

        /** @var ?SegmentStruct $segment */
        $segment = $this->segmentDao->fetchById($idSegment, SegmentStruct::class, 3600);
        if (!$segment) {
            throw new NotFoundException('Segment not found', 404);
        }

        $file = $this->fileDao->getById($segment->id_file, 3600);
        if (!$file || $file->id_project !== (int)$project->id) {
            throw new AuthorizationError('Segment does not belong to this project', 403);
        }

        $this->segmentMetadataDao->upsert(
            $idSegment,
            SegmentMetadataMarshaller::CONTEXT_URL->value,
            $marshalled
        );

        $this->response->json([
            'level' => 'segment',
            'id_segment' => $idSegment,
            'context_url' => $contextUrl,
        ]);
    }
}
