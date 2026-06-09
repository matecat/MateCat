<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Response;
use RuntimeException;
use Utils\Registry\AppConfig;

class ContextUrlSchemaController extends KleinController
{
    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws RuntimeException
     */
    public function schema(): Response
    {
        $schema = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/segment_context_url.json');
        if ($schema === false) {
            throw new RuntimeException('Failed to read segment_context_url.json schema');
        }

        return $this->response->json(json_decode($schema));
    }
}
