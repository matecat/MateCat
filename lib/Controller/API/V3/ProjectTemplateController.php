<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Response;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use stdClass;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

/**
 * @phpstan-import-type HydrationInput from ProjectTemplateStruct
 */
class ProjectTemplateController extends KleinController
{
    private ProjectTemplateDao $projectTemplateDao;

    protected function afterConstruct(): void
    {
        parent::afterConstruct();
        $this->appendValidator(new LoginValidator($this));
        $this->projectTemplateDao = new ProjectTemplateDao($this->db());
    }

    /**
     * @phpstan-return HydrationInput
     * @return object
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws Exception
     */
    private function validateJSON(string $json): object
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator = new JSONValidator('project_template.json', true);
        $validator->validate($validatorObject);

        return $validatorObject->getValue();
    }

    /**
     * Get all entries
     * @throws Exception
     * @throws \DivisionByZeroError
     * @throws \TypeError
     */
    public function all(): Response
    {
        $currentPage = $this->request->param('page') ?? 1;
        $pagination = $this->request->param('perPage') ?? 20;

        if ($pagination > 200) {
            $pagination = 200;
        }

        $uid = $this->getUser()->uid ?? throw new Exception("User UID must not be null");
        return $this->response->json($this->projectTemplateDao->getAllPaginated($uid, "/api/v3/project-template?page=", (int)$currentPage, (int)$pagination));
    }

    /**
     * Get a single entry
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(): Response
    {
        $id = (int)$this->request->param('id');

        $model = $this->projectTemplateDao->getByIdAndUser($id, $this->getUser()->uid ?? throw new Exception("User UID must not be null"));

        if (empty($model)) {
            throw new Exception('Model not found', 404);
        }

        return $this->response->json($model);
    }

    /**
     * Create a new entry
     *
     * @return Response
     * @throws ValidationError
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    public function create(): Response
    {
        // try to create the template
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new ValidationError('Bad Request');
            }

            $json = $this->request->body();
            if ($json === null) {
                throw new ValidationError('Request body is empty');
            }
            $decodedObject = $this->validateJSON($json);

            $struct = $this->projectTemplateDao->createFromJSON($decodedObject, $this->getUser());

            $this->response->code(201);

            return $this->response->json($struct);
        } catch (JSONValidatorException $exception) {
            $this->response->code(400);

            return $this->response->json(['error' => $exception->getFormattedError("project-template")]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                throw new ValidationError("Invalid unique template name");
            } else {
                throw $e;
            }
        }
    }

    /**
     * Update an entry
     *
     * @return Response
     * @throws Exception
     * @throws TypeError
     */
    public function update(): Response
    {
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new ValidationError('Bad Request');
            }

            $id = (int)$this->request->param('id');
            $uid = $this->getUser()->uid ?? throw new TypeError('User not authenticated');
            $json = $this->request->body();
            if ($json === null) {
                throw new ValidationError('Request body is empty');
            }
            $decodedObject = $this->validateJSON($json);

            // mark all templates as not default
            if ($id == 0) {
                $this->projectTemplateDao->markAsNotDefault($uid, 0);

                return $this->response->json($this->projectTemplateDao->getDefaultTemplate($uid));
            }

            $model = $this->projectTemplateDao->getByIdAndUser($id, $uid);

            if (empty($model)) {
                throw new NotFoundException('Model not found');
            }

            $struct = $this->projectTemplateDao->editFromJSON($model, $decodedObject, $id, $this->getUser());

            return $this->response->json($struct);
        } catch (JSONValidatorException $exception) {
            $this->response->code(400);

            return $this->response->json(['error' => $exception->getFormattedError("project-template")]);
        }
    }

    /**
     * Delete an entry
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function delete(): Response
    {
        $id = (int)$this->request->param('id');

        $count = $this->projectTemplateDao->remove($id, $this->getUser()->uid ?? throw new Exception("User UID must not be null"));

        if ($count == 0) {
            throw new NotFoundException('Model not found');
        }

        return $this->response->json([
            'id' => $id
        ]);
    }

    /**
     * This is the Payable Rate Model JSON schema
     *
     * @return Response
     * @throws RuntimeException
     */
    public function schema(): Response
    {
        return $this->response->json($this->getProjectTemplateModelSchema());
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function default(): Response
    {
        $uid = $this->getUser()->uid ?? throw new TypeError('User not authenticated');

        return $this->response->json(
            $this->projectTemplateDao->getDefaultTemplate($uid)
        );
    }

    /**
     * @return stdClass
     * @throws RuntimeException
     */
    private function getProjectTemplateModelSchema(): stdClass
    {
        $skeletonJson = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/project_template.json');
        if ($skeletonJson === false) {
            throw new RuntimeException('Failed to read project_template.json schema');
        }

        $contentJson = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/subfiltering_handlers.json');
        if ($contentJson === false) {
            throw new RuntimeException('Failed to read subfiltering_handlers.json schema');
        }

        $skeletonSchema = JSONValidator::getValidJSONSchema($skeletonJson);
        $contentSchema = JSONValidator::getValidJSONSchema($contentJson);
        $skeletonSchema->properties->subfiltering_handlers = $contentSchema;

        return $skeletonSchema;
    }
}
