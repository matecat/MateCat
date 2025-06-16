<?php

namespace API\Commons\Validators;

use Exception;

class JSONRequestValidator extends Base {

    /**
     * @return void
     * @throws Exception
     */
    protected function _validate(): void {
        if ( !preg_match( '~^application/json~', $this->request->headers()->get( 'Content-Type' ) ) ) {
            throw new Exception('Content type provided not valid (application/json expected)', 405);
        }
    }
}
