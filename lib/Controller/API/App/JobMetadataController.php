<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Jobs\MetadataDao;
use ReflectionException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JobMetadataController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new ChunkPasswordValidator($this));
    }

    /**
     * Delete metadata by key
     * @throws ReflectionException
     * @throws Exception
     */
    public function delete(): void
    {
        $params = $this->sanitizeRequestParams();
        $dao = new MetadataDao();

        $struct = $dao->get($params['id_job'], $params['password'], $params['key']);

        if (empty($struct)) {
            throw new NotFoundException('Metadata not found', 404);
        }

        $dao->delete($params['id_job'], $params['password'], $params['key']);
        $this->response->json([
            'id' => $struct->id
        ]);
    }

    /**
     * Upsert metadata
     * @throws Exception
     */
    public function save(): void
    {
        $dao = new MetadataDao();

        // accept only JSON
        if (!$this->isJsonRequest()) {
            throw new Exception('Bad request', 400);
        }

        $params = $this->sanitizeRequestParams();

        $jsonValidatorObject = new JSONValidatorObject($this->request->body());
        $jsonValidator = new JSONValidator('job_metadata.json', true);
        $jsonValidator->validate($jsonValidatorObject);

        $return = [];
        foreach ($jsonValidatorObject->getValue(true) as $item) {
            $struct = $dao->set(
                $params['id_job'],
                $params['password'],
                $item['key'],
                is_array($item['value']) ? json_encode($item['value']) : $item['value'] ?? 'null'
            );
            $return[] = $struct;
        }

        $this->response->json($return);
    }

    /**
     * @return array
     */
    private function sanitizeRequestParams(): array
    {
        return filter_var_array($this->request->params(), [
            'id_job' => FILTER_SANITIZE_SPECIAL_CHARS,
            'password' => FILTER_SANITIZE_SPECIAL_CHARS,
            'key' => FILTER_SANITIZE_SPECIAL_CHARS,
        ]);
    }
}