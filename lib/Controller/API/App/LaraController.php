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

class LaraController extends KleinController
{
    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    public function translate(): void {
        // id segment
        // source_lang
        // target_lang
        // source
        // mt_engine_id
        // style
        // id_project
        // password
    }
}