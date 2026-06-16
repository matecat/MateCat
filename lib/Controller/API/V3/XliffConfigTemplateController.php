<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Response;
use Model\Xliff\XliffConfigTemplateDao;
use PDOException;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class XliffConfigTemplateController extends KleinController
{
    private ?XliffConfigTemplateDao $xliffConfigTemplateDao = null;

    private function getXliffConfigTemplateDao(): XliffConfigTemplateDao
    {
        return $this->xliffConfigTemplateDao ??= new XliffConfigTemplateDao($this->db());
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @param string $json
     *
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws \Swaggest\JsonSchema\Exception
     * @throws Exception
     */
    protected function validateJSON(string $json): void
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator = new JSONValidator('xliff_parameters_rules_wrapper.json', true);
        $validator->validate($validatorObject);
    }

    /**
     * Get all entries
     *
     * @throws \TypeError
     * @throws \DivisionByZeroError
     */
    public function all(): Response
    {
        try {
            $currentPage = $this->request->param('page') ?? 1;
            $pagination = $this->request->param('perPage') ?? 20;

            if ($pagination > 200) {
                $pagination = 200;
            }

            $uid = $this->getUser()->uid ?? throw new \TypeError('User not authenticated');

            $this->response->status()->setCode(200);

            return $this->response->json($this->getXliffConfigTemplateDao()->getAllPaginated($uid, "/api/v3/xliff-config-template?page=", (int)$currentPage, (int)$pagination));
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
     * @throws \TypeError
     */
    public function get(): Response
    {
        try {
            $id = (int)$this->request->param('id');

            $model = $this->getXliffConfigTemplateDao()->getByIdAndUser($id, $this->getUser()->uid ?? throw new \TypeError('User not authenticated'));

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
     * @throws \TypeError
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
                throw new Exception('Request body is empty', 400);
            }
            $this->validateJSON($json);

            $struct = $this->getXliffConfigTemplateDao()->createFromJSON($json, $this->getUser()->uid ?? throw new \TypeError('User not authenticated'));
            $this->response->code(201);

            return $this->response->json($struct);
        } catch (JSONValidatorException|JsonValidatorGenericException $exception) {
            $errorCode = max($exception->getCode(), 400);
            $this->response->code($errorCode);

            if ($exception instanceof JSONValidatorException) {
                return $this->response->json(['error' => $exception->getFormattedError("xliff-config-template")]);
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
     * @throws \TypeError
     */
    public function update(): Response
    {
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new Exception('Bad Get', 400);
            }

            $id = (int)$this->request->param('id');
            $uid = $this->getUser()->uid ?? throw new \TypeError('User not authenticated');

            $json = $this->request->body();
            if ($json === null) {
                throw new Exception('Request body is empty', 400);
            }
            $this->validateJSON($json);

            $model = $this->getXliffConfigTemplateDao()->getByIdAndUser($id, $uid);

            if (empty($model)) {
                throw new Exception('Model not found', 404);
            }

            $struct = $this->getXliffConfigTemplateDao()->editFromJSON($model, $json, $uid);

            $this->response->code(200);

            return $this->response->json($struct);
        } catch (JSONValidatorException|JsonValidatorGenericException  $exception) {
            $errorCode = max($exception->getCode(), 400);
            $this->response->code($errorCode);

            if ($exception instanceof JSONValidatorException) {
                return $this->response->json(['error' => $exception->getFormattedError("xliff-config-template")]);
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
     * @throws \TypeError
     */
    public function delete(): Response
    {
        try {
            $id = (int)$this->request->paramsNamed()->get('id');
            $uid = $this->getUser()->uid ?? throw new \TypeError('User not authenticated');

            $count = $this->getXliffConfigTemplateDao()->remove($id, $uid);

            if ($count == 0) {
                throw new Exception('Model not found', 404);
            }

            return $this->response->json([
                'id' => $id
            ]);
        } catch (Exception $exception) {
            $errorCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
            $this->response->code($errorCode);

            return $this->response->json([
                'error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * @return Response
     * @throws \RuntimeException
     */
    public function schema(): Response
    {
        return $this->response->json($this->getModelSchema());
    }

    /**
     * @return object
     * @throws \RuntimeException
     */
    private function getModelSchema(): object
    {
        $wrapperPath = AppConfig::$ROOT . '/inc/validation/schema/xliff_parameters_rules_wrapper.json';
        $contentPath = AppConfig::$ROOT . '/inc/validation/schema/xliff_parameters_rules_content.json';

        $wrapperJson = file_get_contents($wrapperPath);
        $contentJson = file_get_contents($contentPath);

        if ($wrapperJson === false || $contentJson === false) {
            throw new \RuntimeException('Failed to read JSON schema files');
        }

        $skeletonSchema = JSONValidator::getValidJSONSchema($wrapperJson);
        $contentSchema = JSONValidator::getValidJSONSchema($contentJson);

        $skeletonSchema->properties->rules->properties = $contentSchema->properties;
        $skeletonSchema->definitions = $contentSchema->definitions;

        return $skeletonSchema;
    }
}