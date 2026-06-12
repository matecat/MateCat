<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use DivisionByZeroError;
use Exception;
use Klein\Response;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use Swaggest\JsonSchema\InvalidValue;
use TypeError;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class PayableRateController extends KleinController
{
    private ?CustomPayableRateDao $customPayableRateDao = null;

    protected function getCustomPayableRateDao(): CustomPayableRateDao
    {
        return $this->customPayableRateDao ??= new CustomPayableRateDao();
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
     * @return Response
     * @throws DivisionByZeroError
     * @throws TypeError
     */
    public function index(): Response
    {
        try {
            $currentPage = $this->request->param('page') ?? 1;
            $pagination = $this->request->param('perPage') ?? 20;

            if ($pagination > 200) {
                $pagination = 200;
            }

            $uid = $this->getUserId();

            return $this->response->json($this->getCustomPayableRateDao()->getAllPaginated($uid, "/api/v3/payable_rate?page=", (int)$currentPage, (int)$pagination));
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
     * @throws TypeError
     */
    public function create(): Response
    {
        // try to create the template
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new Exception('Method not allowed', 405);
            }

            $json = $this->request->body();
            if ($json === null) {
                throw new Exception('Missing request body', 400);
            }
            $this->validateJSON($json);

            $struct = $this->getCustomPayableRateDao()->createFromJSON($json, $this->getUserId());

            $this->response->code(201);

            return $this->response->json($struct);
        } catch (JSONValidatorException|JsonValidatorGenericException $exception) {
            $errorCode = max($exception->getCode(), 400);
            $this->response->code($errorCode);

            if ($exception instanceof JSONValidatorException) {
                return $this->response->json(['error' => $exception->getFormattedError("payable_rate")]);
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
     * @return Response
     */
    public function delete(): Response
    {
        $id = $this->request->param('id');

        try {
            $count = $this->getCustomPayableRateDao()->remove($id, $this->getUserId());

            if ($count == 0) {
                throw new Exception('Model not found', 404);
            }

            return $this->response->json([
                'id' => (int)$id
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
     * @throws TypeError
     */
    public function edit(): Response
    {
        try {
            // accept only JSON
            if (!$this->isJsonRequest()) {
                throw new Exception('Bad Get', 400);
            }

            $id = $this->request->param('id');

            $model = $this->getCustomPayableRateDao()->getByIdAndUser($id, $this->getUserId());
            if (empty($model)) {
                throw new Exception('Model not found', 404);
            }

            $json = $this->request->body();
            if ($json === null) {
                throw new Exception('Missing request body', 400);
            }
            $this->validateJSON($json);

            $struct = $this->getCustomPayableRateDao()->editFromJSON($model, $json);

            $this->response->code(200);

            return $this->response->json($struct);
        } catch (JSONValidatorException|JsonValidatorGenericException|InvalidValue $exception) {
            $errorCode = max($exception->getCode(), 400);
            $this->response->code($errorCode);

            if ($exception instanceof JSONValidatorException) {
                return $this->response->json(['error' => $exception->getFormattedError("payable_rate")]);
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
     * @return Response
     */
    public function view(): Response
    {
        try {
            $id = $this->request->param('id');
            $model = $this->getCustomPayableRateDao()->getByIdAndUser($id, $this->getUserId());

            if (empty($model)) {
                throw new Exception('Model not found', 404);
            }

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
     * This is the Payable Rate Model JSON schema
     *
     * @return Response
     */
    public function schema(): Response
    {
        return $this->response->json(json_decode($this->getPayableRateModelSchema()));
    }

    /**
     * Validate a Payable Rate Model template
     *
     * @return Response
     * @throws TypeError
     */
    public function validate(): Response
    {
        try {
            $json = $this->request->body();
            if ($json === null) {
                throw new Exception('Missing request body', 400);
            }

            $validatorObject = new JSONValidatorObject($json);
            $validator = new JSONValidator($this->getPayableRateModelSchema());
            $validator->validate($validatorObject);

            $errors = $validator->getExceptions();

            if ($validator->isValid()) {
                $customPayableRateStruct = new CustomPayableRateStruct();
                $customPayableRateStruct->hydrateFromJSON($json);
            }

            $code = ($validator->isValid()) ? 200 : 500;

            $this->response->code($code);

            $formattedErrors = [];

            foreach ($errors as $error) {
                if ($error instanceof JSONValidatorException) {
                    $formattedErrors[] = $error->getFormattedError("payable_rate");
                } else {
                    $formattedErrors[] = ['error' => $error->getMessage()];
                }
            }

            return $this->response->json([
                'errors' => $formattedErrors
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
     * @return string
     */
    private function getPayableRateModelSchema(): string
    {
        return file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/payable_rate.json') ?: '';
    }

    /**
     * @throws Exception
     */
    public function default(): void
    {
        $this->response->status()->setCode(200);
        $this->response->json(
            $this->getCustomPayableRateDao()->getDefaultTemplate($this->getUserId())
        );
    }

    /**
     * @param string $json
     *
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws Exception
     */
    private static function validateJSON(string $json): void
    {
        $validatorObject = new JSONValidatorObject($json);
        $validator = new JSONValidator('payable_rate.json', true);
        $validator->validate($validatorObject);
    }

}
