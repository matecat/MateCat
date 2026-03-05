<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;

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