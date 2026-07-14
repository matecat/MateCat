<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use DivisionByZeroError;
use Exception;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Klein\Response;
use Model\Filters\FiltersConfigTemplateDao;
use PDOException;
use RuntimeException;
use TypeError;
use Swaggest\JsonSchema\InvalidValue;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class FiltersConfigTemplateController extends KleinController
{
    private ?FiltersConfigTemplateDao $filtersConfigTemplateDao = null;

    protected function getFiltersConfigTemplateDao(): FiltersConfigTemplateDao
    {
        return $this->filtersConfigTemplateDao ??= new FiltersConfigTemplateDao($this->getDatabase());
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    private function getUserId(): int
    {
        $uid = $this->getUser()->uid;
        if ($uid === null) {
            throw new Exception('User not authenticated', 401);
        }

        return $uid;
    }

    /**
     * @param string $json
     *
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws Exception
     */
    private function validateJSON(string $json): void
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator = new JSONValidator('filters_extraction_parameters.json', true);
        $validator->validate($validatorObject);
    }

    /**
     * Get all entries
     *
     * @throws DivisionByZeroError
     * @throws TypeError
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function all(): Response
    {
        try {
            $currentPage = $this->request->param('page') ?? 1;
            $pagination = $this->request->param('perPage') ?? 20;

            if ($pagination > 200) {
                $pagination = 200;
            }

            $uid = $this->getUserId();

            $this->response->status()->setCode(200);

            return $this->response->json($this->getFiltersConfigTemplateDao()->getAllPaginated($uid, "/api/v3/filters-config-template?page=", (int)$currentPage, (int)$pagination));
        } catch (Exception $exception) {
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode($code);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Get a single entry
     *
     * @throws TypeError
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function get(): Response
    {
        try {
            $id = (int)$this->request->param('id');

            $model = $this->getFiltersConfigTemplateDao()->getByIdAndUser($id, $this->getUserId());

            if (empty($model)) {
                throw new Exception('Model not found', 404);
            }

            $this->response->status()->setCode(200);

            return $this->response->json($model);
        } catch (Exception $exception) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code($errorCode);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Create new entry
     *
     * @return Response
     * @throws TypeError
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function create(): Response
    {
        // try to create the template
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new Exception('Bad Get', 400);
            }

            $json = $this->request->body();
            if ($json === null) {
                throw new Exception('Missing request body', 400);
            }
            $this->validateJSON($json);
            $struct = $this->getFiltersConfigTemplateDao()->createFromJSON($json, $this->getUserId());

            $this->response->code(201);

            return $this->response->json($struct);
        } catch (JSONValidatorException|JsonValidatorGenericException $exception) {
            $this->response->code(400);

            if ($exception instanceof JSONValidatorException) {
                return $this->response->json(['error' => $exception->getFormattedError("filters-config-template")]);
            }

            return $this->response->json(['error' => $exception->getMessage()]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $this->response->code(400);

                return $this->response->json([
                    'error' => "Invalid unique template name"
                ]);
            } else {
                $this->response->code(500);

                return $this->response->json([
                    'error' => $e->getMessage()
                ]);
            }
        } catch (Exception $exception) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code($errorCode);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
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
                throw new Exception('Bad Get', 400);
            }

            $id = (int)$this->request->param('id');
            $uid = $this->getUserId();


            $model = $this->getFiltersConfigTemplateDao()->getByIdAndUser($id, $uid);

            if (empty($model)) {
                throw new Exception('Model not found', 404);
            }

            $json = $this->request->body();
            if ($json === null) {
                throw new Exception('Missing request body', 400);
            }
            $this->validateJSON($json);

            $struct = $this->getFiltersConfigTemplateDao()->editFromJSON($model, $json, $uid);

            $this->response->code(200);

            return $this->response->json($struct);
        } catch (JSONValidatorException|JsonValidatorGenericException|InvalidValue  $exception) {
            $errorCode = max($exception->getCode(), 400);
            $this->response->code($errorCode);

            if ($exception instanceof JSONValidatorException) {
                return $this->response->json(['error' => $exception->getFormattedError("filters-config-template")]);
            }

            return $this->response->json(['error' => $exception->getMessage()]);
        } catch (Exception $exception) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code($errorCode);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Delete an entry
     *
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->paramsNamed()->get('id');
            $uid = $this->getUserId();

            $count = $this->getFiltersConfigTemplateDao()->remove($id, $uid);

            if ($count == 0) {
                throw new Exception('Model not found', 404);
            }

            return $this->response->json([
                'id' => $id
            ]);
        } catch (Exception $exception) {
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode($code);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return Response
     * @throws RuntimeException
     */
    public function schema(): Response
    {
        return $this->response->json($this->getModelSchema());
    }

    /**
     * @return object
     * @throws RuntimeException
     */
    private function getModelSchema(): object
    {
        $schema = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json') ?: '';

        $decoded = json_decode($schema);

        if (!is_object($decoded)) {
            throw new RuntimeException('Unable to load the filters extraction parameters schema');
        }

        return $decoded;
    }
}