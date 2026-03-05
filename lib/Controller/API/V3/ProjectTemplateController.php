<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Response;
use Model\Projects\ProjectTemplateDao;
use PDOException;
use ReflectionException;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class ProjectTemplateController extends KleinController
{
    protected function afterConstruct(): void
    {
        parent::afterConstruct();
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @param $json
     *
     * @return object
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws Exception
     */
    private function validateJSON($json): object
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator = new JSONValidator('project_template.json', true);
        $validator->validate($validatorObject);

        return $validatorObject->getValue();
    }

    /**
     * Get all entries
     * @throws Exception
     */
    public function all(): Response
    {
        $currentPage = $this->request->param('page') ?? 1;
        $pagination = $this->request->param('perPage') ?? 20;

        if ($pagination > 200) {
            $pagination = 200;
        }

        $uid = $this->getUser()->uid;
        return $this->response->json(ProjectTemplateDao::getAllPaginated($uid, "/api/v3/project-template?page=", (int)$currentPage, (int)$pagination));
    }

    /**
     * Get a single entry
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(): Response
    {
        $id = (int)$this->request->param('id');

        $model = ProjectTemplateDao::getByIdAndUser($id, $this->getUser()->uid);

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
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws ReflectionException
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
            $decodedObject = $this->validateJSON($json);

            $struct = ProjectTemplateDao::createFromJSON($decodedObject, $this->getUser());

            $this->response->code(201);

            return $this->response->json($struct);
        } catch (JSONValidatorException $exception) {
            throw new JSONValidatorException($exception->getFormattedError("project-template"));
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
     */
    public function update(): Response
    {
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new ValidationError('Bad Request');
            }

            $id = (int)$this->request->param('id');
            $uid = $this->getUser()->uid;
            $json = $this->request->body();
            $decodedObject = $this->validateJSON($json);

            // mark all templates as not default
            if ($id == 0) {
                ProjectTemplateDao::markAsNotDefault($uid, 0);

                return $this->response->json(ProjectTemplateDao::getDefaultTemplate($uid));
            }

            $model = ProjectTemplateDao::getByIdAndUser($id, $uid);

            if (empty($model)) {
                throw new NotFoundException('Model not found');
            }

            $struct = ProjectTemplateDao::editFromJSON($model, $decodedObject, $id, $this->getUser());

            return $this->response->json($struct);
        } catch (JSONValidatorException $exception) {
            throw new JSONValidatorException($exception->getFormattedError("project-template"));
        }
    }

    /**
     * Delete an entry
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function delete(): Response
    {
        $id = (int)$this->request->param('id');

        $count = ProjectTemplateDao::remove($id, $this->getUser()->uid);

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
     */
    public function schema(): Response
    {
        return $this->response->json(json_decode($this->getProjectTemplateModelSchema()));
    }

    /**
     * @throws Exception
     */
    public function default(): Response
    {
        return $this->response->json(
            ProjectTemplateDao::getDefaultTemplate($this->getUser()->uid)
        );
    }

    /**
     * @return string
     */
    private function getProjectTemplateModelSchema(): string
    {
        $skeletonSchema = JSONValidator::getValidJSONSchema(file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/project_template.json'));
        $contentSchema = JSONValidator::getValidJSONSchema(file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/subfiltering_handlers.json'));
        $skeletonSchema->properties->subfiltering_handlers = $contentSchema;

        return $skeletonSchema;
    }
}
