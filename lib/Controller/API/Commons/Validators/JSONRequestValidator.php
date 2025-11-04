<?php

namespace Controller\API\Commons\Validators;

use Exception;

class JSONRequestValidator extends Base
{

    /**
     * @return void
     * @throws Exception
     */
    protected function _validate(): void
    {
        if (!str_starts_with($this->request->headers()->get('Content-Type'), 'application/json')) {
            throw new Exception('Content type provided not valid (application/json expected)', 405);
        }
    }
}
